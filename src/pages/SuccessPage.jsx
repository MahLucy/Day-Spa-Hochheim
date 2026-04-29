/**
 * SuccessPage — Etapa 4 do checkout.
 *
 * Chegamos aqui de duas formas:
 * 1. Redirecionamento do Mercado Pago após pagamento aprovado:
 *    URL: /confirmacao?order=123&status=approved&...
 * 2. Navegação direta (para testes em dev)
 *
 * O código real do cartão é lido da API após o pagamento ser confirmado.
 */
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import {
  CheckCircle, Copy, Mail, Shield, Calendar,
  MessageCircle, Plus, ArrowLeft,
  Check, AlertCircle, Loader2,
} from 'lucide-react'
import { useCart } from '../hooks/useCart'
import CheckoutStepper from '../components/ui/CheckoutStepper'

const nextSteps = [
  {
    icon: Shield,
    title: 'Pagamento aprovado',
    desc: 'Seu pagamento foi processado com segurança pelo Mercado Pago.',
  },
  {
    icon: Mail,
    title: 'E-mail de confirmação enviado',
    desc: 'Você receberá um e-mail com o cartão presente (QR Code + PDF), código de reserva, serviços incluídos e validade.',
  },
  {
    icon: Calendar,
    title: 'Agendamento',
    desc: 'Entre em contato pelo WhatsApp para agendar. Você tem 30 dias para usar os serviços adquiridos.',
  },
  {
    icon: CheckCircle,
    title: 'Prepare-se para relaxar',
    desc: 'Chegue 15 minutos antes do horário agendado para desfrutar plenamente da experiência.',
  },
  {
    icon: CheckCircle,
    title: 'Sua vivência começa!',
    desc: 'O ambiente está preparado para você. Tenha uma experiência incrível!',
  },
]

export default function SuccessPage() {
  const [searchParams]  = useSearchParams()
  const { clearCart }   = useCart()
  const [copied, setCopied]   = useState(false)
  const [visible, setVisible] = useState(false)
  const [giftCard, setGiftCard] = useState(null)
  const [fetchState, setFetchState] = useState('loading') // loading | success | error | pending

  const mpStatus   = searchParams.get('status')         // approved | pending | failure
  const mpOrderId  = searchParams.get('external_reference') || searchParams.get('order')
  const storedId   = (() => { try { return sessionStorage.getItem('checkout_order_id') } catch { return null } })()
  const orderId    = mpOrderId || storedId

  useEffect(() => {
    const t = setTimeout(() => setVisible(true), 100)
    return () => clearTimeout(t)
  }, [])

  // Busca dados do cartão presente gerado após o pagamento
  useEffect(() => {
    if (!orderId) {
      setFetchState('error')
      return
    }

    // Se o MP retornou status de não-aprovado, não busca
    if (mpStatus && mpStatus !== 'approved') {
      setFetchState('pending')
      return
    }

    let cancelled = false

    const fetchGiftCard = async () => {
      const baseUrl = import.meta.env.VITE_API_URL ?? '/api'

      // Polling: o webhook do MP pode demorar alguns segundos para processar
      for (let attempt = 0; attempt < 6; attempt++) {
        if (cancelled) return

        try {
          const res  = await fetch(`${baseUrl}/orders/${orderId}/giftcard`)
          const data = await res.json()

          if (res.ok && data.data?.code) {
            if (!cancelled) {
              setGiftCard(data.data)
              setFetchState('success')
            }
            return
          }
        } catch { /* continua tentando */ }

        // Espera 2 segundos entre tentativas
        await new Promise(r => setTimeout(r, 2000))
      }

      // Após 6 tentativas (12s), aceita que o e-mail chegará depois
      if (!cancelled) setFetchState('pending')
    }

    fetchGiftCard()
    return () => { cancelled = true }
  }, [orderId, mpStatus])

  const giftCardCode = giftCard?.code ?? '—'

  const handleCopy = () => {
    if (giftCard?.code) {
      navigator.clipboard.writeText(giftCard.code).catch(() => {})
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    }
  }

  // Pagamento pendente (Pix/Boleto aguardando)
  if (mpStatus === 'pending') {
    return (
      <div className="min-h-screen pt-28 pb-12 lg:pt-36 lg:pb-20">
        <div className="page-container max-w-2xl text-center">
          <CheckoutStepper currentStep={4} />
          <div className="w-16 h-16 bg-yellow-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <AlertCircle size={28} className="text-yellow-500" />
          </div>
          <h1 className="font-display text-3xl text-spa-dark font-bold mb-3">Pagamento em processamento</h1>
          <p className="text-base font-body text-spa-muted leading-relaxed mb-8">
            Seu pedido foi criado. Assim que o pagamento for confirmado, você receberá o cartão presente por e-mail.
            Boletos levam até 1 dia útil. Pix é aprovado em segundos.
          </p>
          <Link to="/" onClick={clearCart} className="btn-primary inline-flex items-center gap-2">
            <ArrowLeft size={15} /> Voltar ao início
          </Link>
        </div>
      </div>
    )
  }

  // Pagamento falhou
  if (mpStatus === 'failure') {
    return (
      <div className="min-h-screen pt-28 pb-12 lg:pt-36 lg:pb-20">
        <div className="page-container max-w-2xl text-center">
          <div className="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <AlertCircle size={28} className="text-red-500" />
          </div>
          <h1 className="font-display text-3xl text-spa-dark font-bold mb-3">Pagamento não aprovado</h1>
          <p className="text-base font-body text-spa-muted leading-relaxed mb-8">
            Não foi possível processar seu pagamento. Verifique os dados e tente novamente, ou escolha outro método.
          </p>
          <div className="flex gap-3 justify-center">
            <Link to="/pagamento" className="btn-primary inline-flex items-center gap-2">
              Tentar novamente
            </Link>
            <Link to="/" className="btn-outline inline-flex items-center gap-2">
              Voltar ao início
            </Link>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen pt-28 pb-12 lg:pt-36 lg:pb-20">
      <div className="page-container max-w-2xl">
        <CheckoutStepper currentStep={4} />

        {/* Cabeçalho de confirmação */}
        <div className={`text-center transition-all duration-500 ${visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
          <div className="w-16 h-16 bg-teal-50 rounded-full flex items-center justify-center mx-auto mb-4 border-4 border-spa-accent/30">
            <Check size={28} className="text-spa-accent" strokeWidth={3} />
          </div>
          <p className="text-sm font-body font-semibold tracking-[0.2em] text-spa-mid uppercase mb-2">
            Pagamento aprovado
          </p>
          <h1 className="font-display text-4xl lg:text-5xl text-spa-dark font-bold mb-4">
            Seu cartão está <span className="italic">pronto!</span>
          </h1>
          <p className="text-base font-medium font-body text-spa-muted leading-relaxed mb-10 max-w-xl mx-auto">
            Obrigado por escolher o Day Spa Hochheim. Enviamos o cartão presente para o seu e-mail.
          </p>
        </div>

        {/* Código do cartão */}
        <div className="bg-spa-pale rounded-xl3 p-6 mb-8">
          <p className="text-sm font-body font-semibold text-spa-muted uppercase tracking-widest mb-4 text-center">
            Código do cartão presente
          </p>

          {fetchState === 'loading' && (
            <div className="flex items-center justify-center gap-2 py-4 text-spa-muted font-body text-sm">
              <Loader2 size={16} className="animate-spin" />
              Buscando seu cartão…
            </div>
          )}

          {(fetchState === 'success' || fetchState === 'pending') && (
            <div className="flex items-center justify-between gap-3 bg-white rounded-xl px-4 py-3 shadow-soft">
              <span className={`font-display text-xl lg:text-2xl font-medium tracking-widest ${fetchState === 'pending' ? 'text-spa-muted text-lg' : 'text-spa-dark'}`}>
                {fetchState === 'pending' ? 'Será enviado por e-mail' : giftCardCode}
              </span>
              {fetchState === 'success' && (
                <button onClick={handleCopy}
                  className={`flex items-center gap-1.5 text-xs font-body font-medium px-3 py-2 rounded-lg transition-all ${
                    copied ? 'bg-teal-50 text-teal-700' : 'bg-spa-pale text-spa-muted hover:text-spa-dark hover:bg-gray-100'
                  }`}>
                  {copied ? <Check size={13} /> : <Copy size={13} />}
                  {copied ? 'Copiado!' : 'Copiar'}
                </button>
              )}
            </div>
          )}

          <div className="flex flex-wrap items-center justify-center gap-4 mt-4">
            {[
              { icon: Shield,   label: 'Pagamento seguro' },
              { icon: Mail,     label: 'E-mail enviado' },
              { icon: Calendar, label: 'Validade: 180 dias' },
            ].map(b => (
              <div key={b.label} className="flex items-center gap-1.5">
                <b.icon size={13} className="text-spa-light" />
                <span className="text-xs font-body text-spa-muted">{b.label}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Banner de e-mail */}
        <div className="bg-spa-dark rounded-xl3 p-5 mb-8 flex items-start gap-3">
          <div className="w-8 h-8 bg-spa-accent rounded-full flex items-center justify-center shrink-0 mt-0.5">
            <Mail size={16} className="text-spa-dark" />
          </div>
          <div>
            <p className="text-base font-body font-bold text-white mb-1">Confirmação enviada por e-mail</p>
            <p className="text-sm font-medium font-body text-white/80 leading-relaxed">
              Enviamos o cartão presente com QR Code, PDF para download, código de reserva e orientações de preparo para o seu e-mail cadastrado.
            </p>
          </div>
        </div>

        {/* Serviços do cartão */}
        {giftCard?.items?.length > 0 && (
          <div className="bg-white rounded-xl3 p-6 shadow-card mb-8">
            <h3 className="font-display text-xl text-spa-dark font-bold mb-4">Serviços incluídos no cartão</h3>
            <div className="space-y-3">
              {giftCard.items.map((item, i) => (
                <div key={i} className="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                  <div>
                    <p className="text-sm font-body font-medium text-spa-dark">{item.service_name}</p>
                    <p className="text-xs font-body text-spa-muted">{item.service_category} • {item.service_duration} min</p>
                  </div>
                  <span className="text-sm font-body font-semibold text-spa-mid">x{item.quantity}</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Próximos passos */}
        <div className="mb-10">
          <h3 className="font-display text-2xl text-spa-dark font-bold mb-3">O que acontece agora?</h3>
          <p className="text-base font-medium text-spa-muted font-body mb-8">Acompanhe as próximas etapas.</p>
          <div className="space-y-4">
            {nextSteps.map((step, i) => {
              const Icon = step.icon
              return (
                <div key={i} className="flex items-start gap-4">
                  <div className="flex flex-col items-center">
                    <div className="w-9 h-9 bg-spa-pale rounded-full flex items-center justify-center shrink-0">
                      <Icon size={16} className="text-spa-light" />
                    </div>
                    {i < nextSteps.length - 1 && <div className="w-0.5 h-8 bg-spa-pale mt-1" />}
                  </div>
                  <div className="pb-4">
                    <p className="text-sm font-body font-medium text-spa-dark mb-1">{step.title}</p>
                    <p className="text-xs font-body text-spa-muted leading-relaxed">{step.desc}</p>
                  </div>
                </div>
              )
            })}
          </div>
        </div>

        {/* CTAs */}
        <div className="flex flex-col sm:flex-row gap-3">
          <Link to="/" onClick={clearCart}
            className="flex-1 flex items-center justify-center gap-2 border border-spa-dark text-spa-dark font-body font-medium px-5 py-3.5 rounded-xl hover:bg-spa-pale transition-all text-sm">
            <ArrowLeft size={15} />
            Voltar ao início
          </Link>
          <a href="https://wa.me/5547991151707" target="_blank" rel="noopener noreferrer"
            className="flex-1 flex items-center justify-center gap-2 bg-green-500 text-white font-body font-medium px-5 py-3.5 rounded-xl hover:bg-green-600 transition-all text-sm">
            <MessageCircle size={15} />
            Agendar pelo WhatsApp
          </a>
          <Link to="/servicos"
            className="flex-1 flex items-center justify-center gap-2 bg-spa-dark text-white font-body font-medium px-5 py-3.5 rounded-xl hover:bg-spa-mid transition-all text-sm">
            <Plus size={15} />
            Mais serviços
          </Link>
        </div>
      </div>
    </div>
  )
}
