import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { ChevronLeft, ShieldCheck, Lock, AlertCircle, Info, Copy, Check } from 'lucide-react'
import CheckoutStepper from '../components/ui/CheckoutStepper'
import OrderSummary from '../components/ui/OrderSummary'
import { useCart } from '../hooks/useCart'
import { api, ApiError } from '../services/api'

function loadMPSdk() {
  if (window.MercadoPago) return Promise.resolve()
  if (document.getElementById('mp-sdk-script')) {
    return new Promise(resolve => {
      const poll = setInterval(() => {
        if (window.MercadoPago) { clearInterval(poll); resolve() }
      }, 50)
    })
  }
  return new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.id  = 'mp-sdk-script'
    script.src = 'https://sdk.mercadopago.com/js/v2'
    script.onload  = resolve
    script.onerror = () => reject(new Error('Falha ao carregar o SDK do Mercado Pago.'))
    document.head.appendChild(script)
  })
}

const isDev = import.meta.env.VITE_APP_ENV === 'development'

export default function PaymentPage() {
  const navigate             = useNavigate()
  const { items, giftCard, total, clearCart } = useCart()
  const [phase, setPhase]   = useState('loading') // loading | ready | submitting | error
  const [error, setError]   = useState('')
  const brickRef            = useRef(null)
  const alive               = useRef(true)
  const bootIdRef           = useRef(0)   // incremented each boot; lets stale boots self-cancel
  const [orderId, setOrderId] = useState(null)
  const [pixData, setPixData]     = useState(null) // { qr_code, qr_code_base64, order_id }
  const [pixCopied, setPixCopied] = useState(false)
  const [threeDsOrderId, setThreeDsOrderId] = useState(null)

  useEffect(() => {
    alive.current = true
    return () => {
      alive.current = false
      if (brickRef.current?.unmount) {
        brickRef.current.unmount()
        brickRef.current = null
      }
    }
  }, [])

  // Polls /orders/:id/giftcard every 5s while PIX is pending.
  // Once the gift card exists (payment confirmed via webhook), redirect to success.
  useEffect(() => {
    if (phase !== 'pix-pending' || !pixData?.order_id) return
    const interval = setInterval(async () => {
      try {
        await api.getOrderGiftCard(pixData.order_id)
        clearCart()
        window.location.href = `/confirmacao?status=approved&external_reference=${pixData.order_id}`
      } catch {
        // 404 = still waiting; ignore and keep polling
      }
    }, 5000)
    return () => clearInterval(interval)
  }, [phase, pixData, clearCart])

  // Polls every 5s while 3DS challenge is open in popup.
  // Webhook fires when buyer completes 3DS; gift card will exist when approved.
  useEffect(() => {
    if (phase !== 'three-ds-pending' || !threeDsOrderId) return
    const interval = setInterval(async () => {
      try {
        await api.getOrderGiftCard(threeDsOrderId)
        clearCart()
        window.location.href = `/confirmacao?status=approved&external_reference=${threeDsOrderId}`
      } catch {
        // 404 = still waiting; ignore and keep polling
      }
    }, 5000)
    return () => clearInterval(interval)
  }, [phase, threeDsOrderId, clearCart])

  useEffect(() => {
    if (items.length === 0) return void navigate('/carrinho', { replace: true })
    if (!sessionStorage.getItem('checkout_customer')) return void navigate('/dados-pessoais', { replace: true })
    boot()
  }, [items, navigate])

  async function boot() {
    // Each boot gets a unique id. If a newer boot starts before this one
    // finishes an await, the stale boot exits without touching the DOM.
    const myBootId = ++bootIdRef.current
    const isStale  = () => myBootId !== bootIdRef.current || !alive.current

    console.log(`[Payment] boot #${myBootId} — orderId já existente: ${orderId ?? 'nenhum'}`)
    setPhase('loading')
    setError('')

    brickRef.current?.unmount?.()
    brickRef.current = null
    const mpContainer = document.getElementById('mp-brick-container')
    if (mpContainer) mpContainer.innerHTML = ''

    try {
      await loadMPSdk()
      if (isStale()) { console.log(`[Payment] boot #${myBootId} cancelado após loadMPSdk`); return }

      let currentOrderId = orderId
      if (!currentOrderId) {
        const raw = sessionStorage.getItem('checkout_customer')
        if (!raw) return void navigate('/dados-pessoais', { replace: true })
        const cust = JSON.parse(raw)
        const { recipient_name, recipient_email, recipient_phone, ...customerCore } = cust

        console.log('[Payment] Criando pedido no backend…')
        const orderResp = await api.createOrder({
          customer:         customerCore,
          items:            items.map(i => ({ service_id: i.id, quantity: i.qty })),
          gift_card_format: giftCard === 'printed' ? 'printed' : 'digital',
          recipient:        { name: recipient_name, email: recipient_email, phone: recipient_phone },
        })

        currentOrderId = orderResp.data?.order_id
        console.log('[Payment] Pedido criado — order_id:', currentOrderId)
        if (!currentOrderId) throw new Error('Falha ao registrar pedido. Tente novamente.')

        setOrderId(currentOrderId)
        sessionStorage.setItem('checkout_order_id', String(currentOrderId))
      }

      if (isStale()) { console.log(`[Payment] boot #${myBootId} cancelado após criação do pedido`); return }

      const customer  = JSON.parse(sessionStorage.getItem('checkout_customer') ?? '{}')
      const nameParts = (customer.name ?? '').split(' ')

      const mp      = new window.MercadoPago(import.meta.env.VITE_MP_PUBLIC_KEY, { locale: 'pt-BR' })
      const builder = mp.bricks()

      const controller = await builder.create('payment', 'mp-brick-container', {
        initialization: {
          amount: total,
          payer: {
            firstName: nameParts[0] ?? '',
            lastName:  nameParts.slice(1).join(' ') || '',
            email:     customer.email ?? '',
            entityType: 'individual',
            identification: {
              type: 'CPF',
              number: (customer.cpf ?? '').replace(/\D/g, ''),
            }
          },
        },
        customization: {
          paymentMethods: {
            creditCard:      'all',
            bankTransfer:    'all', // PIX
            maxInstallments: 3,
            minInstallments: 1,
          },
        },
        callbacks: {
          onReady: () => {
            if (alive.current) setPhase('ready')
          },
          onError: (err) => {
            console.group('[MP Brick] onError')
            console.error('type:', err?.type)
            console.error('cause:', err?.cause)
            console.error('field:', err?.field)
            console.error('raw:', err)
            console.groupEnd()
            if (alive.current) {
              setError('Erro no formulário de pagamento. Tente recarregar a página.')
              setPhase('error')
            }
          },
          onSubmit: async ({ formData, additionalData }) => {
            if (!alive.current) return
            setPhase('submitting')
            setError('')

            console.group('[Payment] onSubmit — iniciando pagamento')
            console.log('order_id:', currentOrderId)
            console.log('payment_method_id:', formData?.payment_method_id)
            console.log('payment_type (additionalData):', additionalData?.paymentTypeId)
            console.log('payment_type (formData):', formData?.payment_type)
            console.log('installments:', formData?.installments)
            console.log('issuer_id:', formData?.issuer_id)
            console.log('has token:', !!formData?.token)
            console.log('tracks:', formData?.tracks)
            console.groupEnd()

            try {
              const storedOrderId = currentOrderId
              if (!storedOrderId) {
                console.error('[Payment] Sem storedOrderId — redirecionando para dados-pessoais')
                navigate('/dados-pessoais', { replace: true })
                return
              }

              // Resolve o tipo de pagamento: prioriza additionalData do Brick,
              // depois formData.payment_type, e por último deriva do method_id.
              // O fallback precisa cobrir IDs de boleto que não começam com "boleto"
              // (ex: bolbradesco, bolsantander) — o Brick nem sempre preenche
              // additionalData.paymentTypeId nesses casos.
              const BOLETO_IDS = ['bolbradesco', 'bolsantander', 'boletobbrasil', 'pec']
              const methodId = formData?.payment_method_id ?? ''
              const resolvedPaymentType =
                additionalData?.paymentTypeId ||
                formData?.payment_type ||
                (methodId === 'pix'
                  ? 'bank_transfer'
                  : BOLETO_IDS.includes(methodId) || methodId.startsWith('boleto')
                    ? 'ticket'
                    : methodId.startsWith('deb')
                      ? 'debit_card'
                      : 'credit_card')

              const payload = {
                order_id: storedOrderId,
                ...formData,
                payment_type: resolvedPaymentType,
                device_id: window.MP_DEVICE_SESSION_ID ?? '',
              }
              console.log('[Payment] Enviando para /payments/process:', {
                order_id:          payload.order_id,
                payment_method_id: payload.payment_method_id,
                payment_type:      payload.payment_type,
                installments:      payload.installments,
                has_token:         !!payload.token,
              })

              const payResp   = await api.processPayment(payload)
              const payStatus = payResp.data?.status

              console.group('[Payment] Resposta do backend')
              console.log('status:', payStatus)
              console.log('payment_id:', payResp.data?.payment_id)
              console.log('status_detail:', payResp.data?.status_detail)
              console.log('message:', payResp.data?.message)
              console.log('has qr_code:', !!payResp.data?.qr_code)
              console.log('full response:', payResp)
              console.groupEnd()

              if (payStatus === 'approved') {
                clearCart()
                window.location.href = `/confirmacao?status=approved&external_reference=${storedOrderId}`
              } else if (payStatus === 'pending') {
                if (payResp.data?.three_ds_url) {
                  // 3DS Challenge — open in popup; poll for approval via webhook
                  window.open(payResp.data.three_ds_url, '_blank', 'width=600,height=700,noopener,noreferrer')
                  setThreeDsOrderId(storedOrderId)
                  brickRef.current?.unmount?.()
                  brickRef.current = null
                  setPhase('three-ds-pending')
                } else if (payResp.data?.qr_code) {
                  // PIX — show QR code inline; unmount brick to avoid MP SVG render errors
                  setPixData({
                    qr_code:        payResp.data.qr_code,
                    qr_code_base64: payResp.data.qr_code_base64 ?? null,
                    order_id:       storedOrderId,
                  })
                  brickRef.current?.unmount?.()
                  brickRef.current = null
                  setPhase('pix-pending')
                } else {
                  // Boleto or other pending payment — redirect to confirmation
                  clearCart()
                  window.location.href = `/confirmacao?status=pending&external_reference=${storedOrderId}`
                }
              } else {
                throw new Error(payResp.data?.message ?? 'Pagamento não aprovado. Verifique os dados e tente novamente.')
              }
            } catch (err) {
              console.group('[Payment] ERRO no processamento')
              console.error('message:', err?.message)
              console.error('status HTTP:', err?.status)
              console.error('errors:', err?.errors)
              console.error('raw:', err)
              console.groupEnd()
              if (alive.current) {
                setError(err instanceof ApiError ? err.message : (err.message ?? 'Erro ao processar pagamento.'))
                setPhase('ready')
              }
              throw err // O Brick precisa do reject para reabilitar o botão
            }
          },
        },
      })

      if (isStale()) {
        console.log(`[Payment] boot #${myBootId} cancelado após builder.create`)
        controller.unmount()
        return
      }
      console.log(`[Payment] boot #${myBootId} — MP Brick montado com sucesso`)
      brickRef.current = controller
    } catch (err) {
      console.error('[Payment] Erro no boot:', err)
      if (!isStale()) {
        setError(err.message ?? 'Falha ao carregar o formulário de pagamento.')
        setPhase('error')
      }
    }
  }

  async function handleDevPay() {
    setPhase('submitting')
    setError('')
    try {
      // FIX: reutiliza orderId existente se disponível (mesmo padrão do boot)
      let currentOrderId = orderId
      if (!currentOrderId) {
        const raw = sessionStorage.getItem('checkout_customer')
        if (!raw) return void navigate('/dados-pessoais', { replace: true })
        const cust = JSON.parse(raw)
        const { recipient_name, recipient_email, recipient_phone, ...customerCore } = cust

        const orderResp = await api.createOrder({
          customer:         customerCore,
          items:            items.map(i => ({ service_id: i.id, quantity: i.qty })),
          gift_card_format: giftCard === 'printed' ? 'printed' : 'digital',
          recipient:        { name: recipient_name, email: recipient_email, phone: recipient_phone },
        })

        currentOrderId = orderResp.data?.order_id
        if (!currentOrderId) throw new Error('Falha ao registrar pedido. Tente novamente.')
        setOrderId(currentOrderId)
        sessionStorage.setItem('checkout_order_id', String(currentOrderId))
      }

      await api.mockApprovePayment(currentOrderId)
      clearCart()
      window.location.href = `/confirmacao?status=approved&external_reference=${currentOrderId}`
    } catch (err) {
      setError(err instanceof ApiError ? err.message : (err.message ?? 'Erro ao simular pagamento.'))
      setPhase('ready')
    }
  }

  function handleCopyPix() {
    navigator.clipboard.writeText(pixData.qr_code).then(() => {
      setPixCopied(true)
      setTimeout(() => setPixCopied(false), 3000)
    })
  }

  return (
    <div className="min-h-screen pt-28 pb-12 lg:pt-36 lg:pb-20">
      <div className="page-container">
        <CheckoutStepper currentStep={3} />

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Coluna principal */}
          <div className="lg:col-span-2">
            <h1 className="font-display text-4xl lg:text-5xl text-spa-dark font-bold mb-3">
              Forma de pagamento
            </h1>

            {/* Aviso individual */}
            <div className="flex items-start gap-3 mb-5 p-4 bg-amber-50 border border-amber-200 rounded-xl">
              <Info size={18} className="text-amber-600 shrink-0 mt-0.5" />
              <p className="text-sm font-body text-amber-800 leading-relaxed">
                <strong>Atenção:</strong> A compra do cartão presente é individual — somente para 1 pessoa.
                Não é possível dividir os serviços entre pessoas diferentes.
              </p>
            </div>

            {/* Badge de segurança */}
            <div className="flex items-start gap-2.5 mb-8 p-4 bg-spa-pale rounded-xl border border-spa-accent/30">
              <Lock size={16} className="text-spa-mid mt-0.5 shrink-0" />
              <p className="text-sm font-body text-spa-dark leading-relaxed">
                <strong>Ambiente 100% seguro.</strong> Os dados do cartão são criptografados diretamente
                no seu navegador pelo Mercado Pago (PCI-DSS). Nenhum dado financeiro transita pelos
                nossos servidores.
              </p>
            </div>

            {/* Skeleton de carregamento */}
            {phase === 'loading' && (
              <div className="bg-white rounded-xl3 p-8 shadow-card">
                <div className="flex items-center justify-center gap-2 text-spa-muted font-body text-sm mb-6">
                  <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                  </svg>
                  Carregando formulário de pagamento seguro…
                </div>
                <div className="space-y-3 animate-pulse">
                  {[1, 2, 3].map(n => <div key={n} className="h-12 bg-gray-100 rounded-lg" />)}
                </div>
              </div>
            )}

            {/* Container do Brick — sempre no DOM para o SDK montar */}
            <div
              id="mp-brick-container"
              className={['loading', 'pix-pending', 'three-ds-pending'].includes(phase) ? 'h-0 overflow-hidden' : ''}
            />

            {/* PIX — QR Code inline */}
            {phase === 'pix-pending' && pixData && (
              <div className="bg-white rounded-2xl p-6 shadow-card border border-green-100">
                <div className="flex items-center gap-2 mb-5">
                  <div className="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center shrink-0">
                    <Check size={16} className="text-green-600" />
                  </div>
                  <div>
                    <p className="font-body font-semibold text-spa-dark text-sm">PIX gerado com sucesso!</p>
                    <p className="text-xs text-spa-muted font-body">Válido por 30 minutos</p>
                  </div>
                </div>

                {pixData.qr_code_base64 && (
                  <div className="flex justify-center mb-4">
                    <img
                      src={`data:image/png;base64,${pixData.qr_code_base64}`}
                      alt="QR Code PIX"
                      className="w-48 h-48 border border-gray-200 rounded-xl"
                    />
                  </div>
                )}

                <p className="text-xs text-center text-spa-muted font-body mb-2">
                  ou use o <strong>Pix Copia e Cola:</strong>
                </p>
                <div className="flex items-center gap-2 p-3 bg-gray-50 border border-gray-200 rounded-xl">
                  <code className="flex-1 text-xs text-spa-dark font-mono break-all leading-relaxed">
                    {pixData.qr_code}
                  </code>
                  <button
                    onClick={handleCopyPix}
                    className="shrink-0 p-2 rounded-lg bg-white border border-gray-200 hover:border-spa-accent transition-colors"
                    title="Copiar código PIX"
                  >
                    {pixCopied
                      ? <Check size={14} className="text-green-600" />
                      : <Copy size={14} className="text-spa-muted" />
                    }
                  </button>
                </div>
                {pixCopied && (
                  <p className="mt-1.5 text-center text-xs text-green-600 font-body">Código copiado!</p>
                )}

                <div className="mt-4 flex items-center gap-2 p-3 bg-amber-50 border border-amber-100 rounded-xl">
                  <svg className="animate-spin w-3.5 h-3.5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                  </svg>
                  <p className="text-xs font-body text-amber-700">
                    Aguardando confirmação do pagamento… Esta página atualiza automaticamente.
                  </p>
                </div>
              </div>
            )}

            {/* 3DS Challenge — autenticação pendente no popup */}
            {phase === 'three-ds-pending' && (
              <div className="bg-white rounded-2xl p-6 shadow-card border border-blue-100">
                <div className="flex items-center gap-2 mb-5">
                  <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center shrink-0">
                    <Lock size={16} className="text-blue-600" />
                  </div>
                  <div>
                    <p className="font-body font-semibold text-spa-dark text-sm">Autenticação do banco necessária</p>
                    <p className="text-xs text-spa-muted font-body">Uma janela do banco foi aberta para verificar sua identidade</p>
                  </div>
                </div>
                <div className="p-4 bg-blue-50 border border-blue-100 rounded-xl text-sm font-body text-blue-800 leading-relaxed mb-4">
                  <strong>Como concluir:</strong> Complete a verificação na janela que foi aberta pelo seu banco (pode ser um código SMS, biometria ou senha). Esta página aguardará a confirmação automaticamente.
                </div>
                <div className="flex items-center gap-2 p-3 bg-amber-50 border border-amber-100 rounded-xl">
                  <svg className="animate-spin w-3.5 h-3.5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                  </svg>
                  <p className="text-xs font-body text-amber-700">Aguardando confirmação… Esta página atualiza automaticamente.</p>
                </div>
              </div>
            )}

            {/* Overlay de processamento */}
            {phase === 'submitting' && (
              <div className="mt-4 flex items-center gap-2 p-4 bg-spa-pale border border-spa-accent/30 rounded-xl text-sm text-spa-dark font-body">
                <svg className="animate-spin w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                </svg>
                Processando seu pagamento com segurança…
              </div>
            )}

            {/* Mensagem de erro */}
            {error && (
              <div className="mt-4 flex items-start gap-2 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-body">
                <AlertCircle size={16} className="shrink-0 mt-0.5" />
                <div>
                  <p>{error}</p>
                  {phase === 'error' && (
                    <button onClick={boot} className="mt-2 underline font-medium text-red-800">
                      Tentar novamente
                    </button>
                  )}
                </div>
              </div>
            )}

            {/* Painel de simulação — apenas em desenvolvimento */}
            {isDev && (
              <div className="mt-6 p-4 border-2 border-dashed border-amber-400 rounded-xl bg-amber-50">
                <p className="text-xs font-mono font-bold text-amber-700 mb-2">AMBIENTE DE DESENVOLVIMENTO</p>

                {/* Dados do cartão de teste (sandbox MP) */}
                <div className="mb-3 p-3 bg-white border border-amber-300 rounded-lg font-mono text-xs text-amber-900 space-y-0.5">
                  <p className="font-bold text-amber-700 mb-1">Cartão de teste (sandbox):</p>
                  <p><span className="text-amber-500">Número:</span> 5031 4332 1540 6351</p>
                  <p><span className="text-amber-500">Nome do titular:</span> <strong>APRO</strong> (obrigatório para aprovar)</p>
                  <p><span className="text-amber-500">Validade:</span> qualquer data futura</p>
                  <p><span className="text-amber-500">CVV:</span> 123</p>
                </div>

                <button
                  onClick={handleDevPay}
                  disabled={phase === 'submitting'}
                  className="w-full py-2.5 px-4 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-body font-medium text-sm transition-colors disabled:opacity-50"
                >
                  Simular pagamento aprovado (TESTE)
                </button>
              </div>
            )}

            {/* Ações inferiores */}
            <div className="mt-6 flex items-center justify-between">
              <button
                onClick={() => navigate('/dados-pessoais')}
                disabled={phase === 'submitting'}
                className="flex items-center gap-1.5 text-sm font-body text-spa-muted hover:text-spa-dark transition-colors px-4 py-3 rounded-xl hover:bg-gray-50 disabled:opacity-50"
              >
                <ChevronLeft size={16} />
                Voltar
              </button>

              <div className="flex flex-wrap items-center gap-4">
                {[
                  { Icon: ShieldCheck, label: 'SSL 256-bit' },
                  { Icon: Lock,        label: 'PCI-DSS' },
                  { Icon: ShieldCheck, label: 'Mercado Pago' },
                ].map(({ Icon, label }) => (
                  <div key={label} className="flex items-center gap-1.5 text-xs font-body text-spa-muted">
                    <Icon size={12} className="text-spa-light" />
                    {label}
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Resumo do pedido */}
          <div className="lg:col-span-1">
            <div className="lg:sticky lg:top-24">
              <OrderSummary />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
