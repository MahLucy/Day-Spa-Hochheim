/**
 * PaymentPage — Etapa 3 do checkout.
 *
 * Segurança:
 * - Dados de cartão NUNCA são coletados neste formulário.
 * - O usuário seleciona apenas o método de pagamento.
 * - Ao confirmar, criamos o pedido no backend e obtemos um
 *   preference_id do Mercado Pago.
 * - O usuário é redirecionado para o ambiente seguro do MP
 *   (init_point) onde o MP coleta os dados do cartão/PIX/boleto
 *   em ambiente 100% PCI-DSS compliant.
 * - Nenhum dado de pagamento transita pelos nossos servidores.
 */
import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { ChevronLeft, ShieldCheck, CreditCard, Smartphone, Building2, AlertCircle, Lock } from 'lucide-react'
import CheckoutStepper from '../components/ui/CheckoutStepper'
import OrderSummary from '../components/ui/OrderSummary'
import { useCart } from '../hooks/useCart'
import { api, ApiError } from '../services/api'

const paymentMethods = [
  { id: 'pix',    label: 'Pix',               icon: Smartphone,  desc: 'Aprovação imediata • QR Code gerado pelo Mercado Pago' },
  { id: 'credit', label: 'Cartão de crédito', icon: CreditCard,  desc: 'Até 12x sem juros • Dados inseridos no ambiente seguro MP' },
  { id: 'debit',  label: 'Cartão de débito',  icon: CreditCard,  desc: 'Débito à vista • Dados inseridos no ambiente seguro MP' },
  { id: 'boleto', label: 'Boleto bancário',   icon: Building2,   desc: 'Vencimento em 1 dia útil' },
]

export default function PaymentPage() {
  const navigate                   = useNavigate()
  const { items, giftCard, total } = useCart()
  const [method, setMethod]        = useState('pix')
  const [loading, setLoading]      = useState(false)
  const [error, setError]          = useState('')

  // Guardas de navegação
  useEffect(() => {
    if (items.length === 0) { navigate('/carrinho', { replace: true }); return }
    const hasCustomer = sessionStorage.getItem('checkout_customer')
    if (!hasCustomer) navigate('/dados-pessoais', { replace: true })
  }, [items, navigate])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)

    try {
      // Recupera dados do cliente salvos na etapa anterior
      const customerRaw = sessionStorage.getItem('checkout_customer')
      if (!customerRaw) {
        navigate('/dados-pessoais', { replace: true })
        return
      }
      const customer = JSON.parse(customerRaw)

      // Monta payload do pedido
      const orderPayload = {
        customer,
        items: items.map(item => ({
          service_id: item.id,
          quantity:   item.qty,
        })),
        gift_card_format: giftCard === 'printed' ? 'printed' : 'digital',
      }

      // 1. Cria pedido no backend
      const orderResponse = await api.createOrder(orderPayload)
      const orderId = orderResponse.data?.order_id
      if (!orderId) throw new Error('Falha ao criar pedido. Tente novamente.')

      // Persiste order_id para a SuccessPage
      try { sessionStorage.setItem('checkout_order_id', String(orderId)) } catch { /* ignora */ }

      // 2. Cria preferência de pagamento no Mercado Pago
      const prefResponse = await api.createPaymentPreference(orderId)
      const initPoint    = prefResponse.data?.init_point
      const sandboxPoint = prefResponse.data?.sandbox_init_point

      if (!initPoint) throw new Error('Falha ao iniciar pagamento. Tente novamente.')

      // 3. Redireciona para o ambiente seguro do Mercado Pago
      // Em desenvolvimento (sandbox), usa sandbox_init_point; em produção, init_point
      const isDev       = import.meta.env.VITE_APP_ENV !== 'production'
      const redirectUrl = (isDev && sandboxPoint) ? sandboxPoint : initPoint

      // Pequena pausa para o spinner ser visível
      await new Promise(r => setTimeout(r, 400))
      window.location.href = redirectUrl

    } catch (err) {
      setLoading(false)
      if (err instanceof ApiError) {
        setError(err.message)
      } else {
        setError(err.message ?? 'Ocorreu um erro inesperado. Tente novamente.')
      }
    }
  }

  return (
    <div className="min-h-screen pt-28 pb-12 lg:pt-36 lg:pb-20">
      <div className="page-container">
        <CheckoutStepper currentStep={3} />

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Form */}
          <div className="lg:col-span-2">
            <h1 className="font-display text-4xl lg:text-5xl text-spa-dark font-bold mb-3">
              Forma de pagamento
            </h1>

            {/* Badge de segurança */}
            <div className="flex items-start gap-2.5 mb-10 p-4 bg-spa-pale rounded-xl border border-spa-accent/30">
              <Lock size={16} className="text-spa-mid mt-0.5 shrink-0" />
              <p className="text-sm font-body text-spa-dark leading-relaxed">
                <strong>Seus dados financeiros estão protegidos.</strong> Ao confirmar, você será redirecionado para o ambiente
                certificado PCI-DSS do Mercado Pago. Nenhum dado de cartão passa pelos nossos servidores.
              </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
              {/* Seleção de método */}
              <div className="bg-white rounded-xl3 p-6 shadow-card">
                <div className="flex items-center gap-3 mb-5">
                  <div className="w-7 h-7 rounded-full bg-spa-dark text-white text-sm font-body font-medium flex items-center justify-center shrink-0">1</div>
                  <h3 className="font-display text-2xl text-spa-dark font-bold">Selecione o método</h3>
                </div>
                <div className="space-y-3">
                  {paymentMethods.map(m => {
                    const Icon = m.icon
                    return (
                      <label key={m.id} className={`flex items-center gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all ${
                        method === m.id ? 'border-spa-dark bg-spa-pale' : 'border-gray-100 hover:border-gray-200'
                      }`}>
                        <input type="radio" name="method" className="sr-only"
                          checked={method === m.id} onChange={() => setMethod(m.id)} />
                        <div className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 ${
                          method === m.id ? 'bg-spa-dark text-white' : 'bg-gray-100 text-spa-muted'
                        }`}>
                          <Icon size={18} />
                        </div>
                        <div className="flex-1">
                          <p className="text-base font-body font-bold text-spa-dark">{m.label}</p>
                          <p className="text-xs font-body font-medium text-spa-muted">{m.desc}</p>
                        </div>
                        <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 ${
                          method === m.id ? 'border-spa-dark' : 'border-gray-300'
                        }`}>
                          {method === m.id && <div className="w-2.5 h-2.5 rounded-full bg-spa-dark" />}
                        </div>
                      </label>
                    )
                  })}
                </div>
              </div>

              {/* Informações do método selecionado */}
              <div className="bg-white rounded-xl3 p-6 shadow-card">
                <div className="flex items-center gap-3 mb-4">
                  <div className="w-7 h-7 rounded-full bg-spa-dark text-white text-sm font-body font-medium flex items-center justify-center shrink-0">2</div>
                  <h3 className="font-display text-2xl text-spa-dark font-bold">Próximo passo</h3>
                </div>

                {method === 'pix' && (
                  <div className="flex items-start gap-3 text-sm font-body text-spa-muted">
                    <Smartphone size={18} className="text-spa-mid shrink-0 mt-0.5" />
                    <p>Após confirmar, você será redirecionado para o Mercado Pago onde um <strong className="text-spa-dark">QR Code Pix</strong> será gerado. O pagamento é aprovado em segundos.</p>
                  </div>
                )}
                {(method === 'credit' || method === 'debit') && (
                  <div className="flex items-start gap-3 text-sm font-body text-spa-muted">
                    <CreditCard size={18} className="text-spa-mid shrink-0 mt-0.5" />
                    <p>Após confirmar, você será redirecionado para o checkout seguro do Mercado Pago para inserir os dados do <strong className="text-spa-dark">{method === 'credit' ? 'cartão de crédito' : 'cartão de débito'}</strong>. {method === 'credit' ? 'Parcelamento disponível em até 12x.' : ''}</p>
                  </div>
                )}
                {method === 'boleto' && (
                  <div className="flex items-start gap-3 text-sm font-body text-spa-muted">
                    <Building2 size={18} className="text-spa-mid shrink-0 mt-0.5" />
                    <p>Após confirmar, um <strong className="text-spa-dark">boleto bancário</strong> será gerado com vencimento em 1 dia útil. O cartão presente é enviado após a compensação.</p>
                  </div>
                )}

                {/* Total destacado */}
                <div className="mt-5 pt-5 border-t border-gray-100 flex items-center justify-between">
                  <span className="text-sm font-body text-spa-muted">Total a pagar</span>
                  <span className="text-xl font-display font-bold text-spa-dark">
                    R$ {total.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                  </span>
                </div>
              </div>

              {/* Erro global */}
              {error && (
                <div className="flex items-start gap-2 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-body">
                  <AlertCircle size={16} className="shrink-0 mt-0.5" />
                  <p>{error}</p>
                </div>
              )}

              {/* Ações */}
              <div className="flex items-center justify-between pt-2">
                <button type="button" onClick={() => navigate('/dados-pessoais')}
                  disabled={loading}
                  className="flex items-center gap-1.5 text-sm font-body text-spa-muted hover:text-spa-dark transition-colors px-4 py-3 rounded-xl hover:bg-gray-50 disabled:opacity-50">
                  <ChevronLeft size={16} />
                  Voltar
                </button>

                <button type="submit" disabled={loading}
                  className="btn-primary flex items-center gap-2 py-3.5 px-8 disabled:opacity-60 disabled:cursor-not-allowed min-w-[200px] justify-center">
                  {loading ? (
                    <>
                      <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                      </svg>
                      Preparando pagamento...
                    </>
                  ) : (
                    <>
                      <ShieldCheck size={16} />
                      Confirmar e pagar
                    </>
                  )}
                </button>
              </div>

              {/* Selos de segurança */}
              <div className="flex flex-wrap items-center justify-center gap-4 pt-2 border-t border-gray-100">
                {[
                  { icon: ShieldCheck, label: 'SSL 256-bit' },
                  { icon: Lock,        label: 'PCI-DSS' },
                  { icon: ShieldCheck, label: 'Mercado Pago' },
                ].map(({ icon: Icon, label }) => (
                  <div key={label} className="flex items-center gap-1.5 text-xs font-body text-spa-muted">
                    <Icon size={12} className="text-spa-light" />
                    {label}
                  </div>
                ))}
              </div>
            </form>
          </div>

          {/* Resumo */}
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
