import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { ChevronLeft, ShieldCheck, Lock, AlertCircle, Info } from 'lucide-react'
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
  const isBooting           = useRef(false)

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

  useEffect(() => {
    if (items.length === 0) return void navigate('/carrinho', { replace: true })
    if (!sessionStorage.getItem('checkout_customer')) return void navigate('/dados-pessoais', { replace: true })
    
    if (isBooting.current) return
    isBooting.current = true
    
    boot()
  }, [items, navigate])

  async function boot() {
    setPhase('loading')
    setError('')

    // Desmonta brick anterior se houver (retry)
    brickRef.current?.unmount?.()
    brickRef.current = null

    try {
      await loadMPSdk()
      if (!alive.current) return

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
            creditCard:   'all',
            debitCard:    'all',
            ticket:       'all', // boleto
            bankTransfer: 'all', // PIX
          },
        },
        callbacks: {
          onReady: () => {
            if (alive.current) setPhase('ready')
          },
          onError: (err) => {
            console.error('[MP Brick]', err)
            if (alive.current) {
              setError('Erro no formulário de pagamento. Tente recarregar a página.')
              setPhase('error')
            }
          },
          onSubmit: async ({ formData }) => {
            if (!alive.current) return
            setPhase('submitting')
            setError('')

            try {
              const raw = sessionStorage.getItem('checkout_customer')
              if (!raw) return void navigate('/dados-pessoais', { replace: true })
              const cust = JSON.parse(raw)
              const { recipient_name, recipient_email, recipient_phone, ...customerCore } = cust

              // 1. Cria o pedido
              const orderResp = await api.createOrder({
                customer:         customerCore,
                items:            items.map(i => ({ service_id: i.id, quantity: i.qty })),
                gift_card_format: giftCard === 'printed' ? 'printed' : 'digital',
                recipient:        { name: recipient_name, email: recipient_email, phone: recipient_phone },
              })

              const orderId = orderResp.data?.order_id
              if (!orderId) throw new Error('Falha ao registrar pedido. Tente novamente.')
              sessionStorage.setItem('checkout_order_id', String(orderId))

              // 2. Processa o pagamento via checkout transparente
              const payResp  = await api.processPayment({ order_id: orderId, ...formData })
              const payStatus = payResp.data?.status

              if (payStatus === 'approved') {
                clearCart()
                window.location.href = `/confirmacao?status=approved&external_reference=${orderId}`
              } else if (payStatus === 'pending') {
                clearCart()
                window.location.href = `/confirmacao?status=pending&external_reference=${orderId}`
              } else {
                throw new Error(payResp.data?.message ?? 'Pagamento não aprovado. Verifique os dados e tente novamente.')
              }
            } catch (err) {
              if (alive.current) {
                setError(err instanceof ApiError ? err.message : (err.message ?? 'Erro ao processar pagamento.'))
                setPhase('ready')
              }
              throw err // O Brick precisa do reject para reabilitar o botão
            }
          },
        },
      })

      if (alive.current) brickRef.current = controller
    } catch (err) {
      if (alive.current) {
        setError(err.message ?? 'Falha ao carregar o formulário de pagamento.')
        setPhase('error')
      }
    }
  }

  async function handleDevPay() {
    setPhase('submitting')
    setError('')
    try {
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

      const orderId = orderResp.data?.order_id
      if (!orderId) throw new Error('Falha ao registrar pedido. Tente novamente.')
      sessionStorage.setItem('checkout_order_id', String(orderId))

      await api.mockApprovePayment(orderId)
      clearCart()
      window.location.href = `/confirmacao?status=approved&external_reference=${orderId}`
    } catch (err) {
      setError(err instanceof ApiError ? err.message : (err.message ?? 'Erro ao simular pagamento.'))
      setPhase('ready')
    }
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
              className={phase === 'loading' ? 'h-0 overflow-hidden' : ''}
            />

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
                <p className="text-xs font-mono font-bold text-amber-700 mb-1">AMBIENTE DE DESENVOLVIMENTO</p>
                <p className="text-sm text-amber-800 font-body mb-3">
                  Simula um pagamento aprovado sem passar pelo Mercado Pago.
                </p>
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
