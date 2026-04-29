import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import {
  CheckCircle, XCircle, Clock, AlertTriangle,
  Calendar, Gift, Loader2, ArrowLeft, MessageCircle,
} from 'lucide-react'
import { api } from '../services/api'

const STATUS_CONFIG = {
  active: {
    label: 'Cartão Válido',
    sublabel: 'Este cartão está ativo e pode ser utilizado',
    icon: CheckCircle,
    iconColor: 'text-emerald-600',
    badgeBg: 'bg-emerald-50 border-emerald-200',
    badgeText: 'text-emerald-700',
    heroBg: 'bg-emerald-50',
    dot: 'bg-emerald-500',
  },
  redeemed: {
    label: 'Cartão Utilizado',
    sublabel: 'Este cartão já foi utilizado',
    icon: CheckCircle,
    iconColor: 'text-blue-600',
    badgeBg: 'bg-blue-50 border-blue-200',
    badgeText: 'text-blue-700',
    heroBg: 'bg-blue-50',
    dot: 'bg-blue-500',
  },
  expired: {
    label: 'Cartão Expirado',
    sublabel: 'A validade deste cartão encerrou',
    icon: Clock,
    iconColor: 'text-gray-500',
    badgeBg: 'bg-gray-100 border-gray-200',
    badgeText: 'text-gray-600',
    heroBg: 'bg-gray-50',
    dot: 'bg-gray-400',
  },
  cancelled: {
    label: 'Cartão Cancelado',
    sublabel: 'Este cartão foi cancelado',
    icon: XCircle,
    iconColor: 'text-red-600',
    badgeBg: 'bg-red-50 border-red-200',
    badgeText: 'text-red-700',
    heroBg: 'bg-red-50',
    dot: 'bg-red-500',
  },
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const [y, m, d] = dateStr.slice(0, 10).split('-')
  return `${d}/${m}/${y}`
}

function formatDatetime(datetimeStr) {
  if (!datetimeStr) return '—'
  const dt = new Date(datetimeStr)
  return dt.toLocaleDateString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function formatCurrency(value) {
  if (value == null) return '—'
  return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

export default function GiftCardPage() {
  const { code } = useParams()
  const [card, setCard] = useState(null)
  const [state, setState] = useState('loading') // loading | success | not_found | error
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const t = setTimeout(() => setVisible(true), 80)
    return () => clearTimeout(t)
  }, [])

  useEffect(() => {
    if (!code) { setState('not_found'); return }

    api.validateGiftCard(code.toUpperCase())
      .then(res => {
        setCard(res.data ?? res)
        setState('success')
      })
      .catch(err => {
        setState(err.status === 404 ? 'not_found' : 'error')
      })
  }, [code])

  if (state === 'loading') {
    return (
      <div className="min-h-screen flex items-center justify-center bg-spa-cream">
        <div className="flex flex-col items-center gap-3 text-spa-muted">
          <Loader2 size={32} className="animate-spin text-spa-mid" />
          <p className="font-body text-sm">Verificando cartão…</p>
        </div>
      </div>
    )
  }

  if (state === 'not_found') {
    return (
      <div className="min-h-screen pt-28 pb-16 lg:pt-36 bg-spa-cream">
        <div className="page-container max-w-lg text-center">
          <div className="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-5">
            <AlertTriangle size={28} className="text-red-500" />
          </div>
          <h1 className="font-display text-2xl text-spa-dark font-bold mb-3">Cartão não encontrado</h1>
          <p className="font-body text-sm text-spa-muted mb-8 leading-relaxed">
            O código <span className="font-semibold text-spa-dark">{code}</span> não foi localizado.
            Verifique se o QR Code foi escaneado corretamente.
          </p>
          <Link to="/" className="btn-primary inline-flex items-center gap-2">
            <ArrowLeft size={15} /> Ir para o início
          </Link>
        </div>
      </div>
    )
  }

  if (state === 'error') {
    return (
      <div className="min-h-screen pt-28 pb-16 lg:pt-36 bg-spa-cream">
        <div className="page-container max-w-lg text-center">
          <div className="w-16 h-16 bg-yellow-50 rounded-full flex items-center justify-center mx-auto mb-5">
            <AlertTriangle size={28} className="text-yellow-500" />
          </div>
          <h1 className="font-display text-2xl text-spa-dark font-bold mb-3">Erro ao verificar cartão</h1>
          <p className="font-body text-sm text-spa-muted mb-8 leading-relaxed">
            Não foi possível verificar este cartão agora. Tente novamente em instantes.
          </p>
          <button onClick={() => { setState('loading'); api.validateGiftCard(code.toUpperCase()).then(res => { setCard(res.data ?? res); setState('success') }).catch(() => setState('error')) }}
            className="btn-primary">
            Tentar novamente
          </button>
        </div>
      </div>
    )
  }

  const cfg = STATUS_CONFIG[card.status] ?? STATUS_CONFIG.active
  const StatusIcon = cfg.icon
  const isValid = card.status === 'active'

  return (
    <div className={`min-h-screen pt-28 pb-16 lg:pt-36 transition-all duration-500 ${visible ? 'opacity-100' : 'opacity-0'}`}>
      <div className="page-container max-w-2xl">

        {/* Status banner */}
        <div className={`rounded-xl3 border px-5 py-4 mb-6 flex items-center gap-3 ${cfg.badgeBg}`}>
          <span className={`w-2.5 h-2.5 rounded-full shrink-0 ${cfg.dot}`} />
          <div className="flex-1">
            <p className={`font-body font-semibold text-sm ${cfg.badgeText}`}>{cfg.label}</p>
            <p className={`font-body text-xs mt-0.5 opacity-80 ${cfg.badgeText}`}>{cfg.sublabel}</p>
          </div>
          <StatusIcon size={22} className={cfg.iconColor} />
        </div>

        {/* Hero card */}
        <div className="bg-spa-dark rounded-xl3 overflow-hidden mb-6 shadow-card">
          {/* Top decorative strip */}
          <div className="h-1 bg-gradient-to-r from-spa-accent via-spa-light to-spa-mid" />

          <div className="p-6 sm:p-8">
            {/* Logo + title */}
            <div className="flex items-center gap-3 mb-6">
              <img src="/logo.png" alt="Day Spa Hochheim" className="h-10 w-auto object-contain brightness-0 invert" />
              <div>
                <p className="font-body text-xs text-white/50 uppercase tracking-widest">Clínica Hochheim</p>
                <p className="font-display text-sm text-white font-semibold">Day Spa</p>
              </div>
            </div>

            {/* Gift label */}
            <div className="flex items-center gap-2 mb-3">
              <Gift size={15} className="text-spa-accent" />
              <span className="font-body text-xs text-spa-accent uppercase tracking-widest font-semibold">
                Cartão Presente
              </span>
            </div>

            {/* Code */}
            <p className="font-display text-3xl sm:text-4xl font-bold text-white tracking-[0.18em] mb-1">
              {card.code}
            </p>

            {/* Recipient */}
            {card.recipient_name && (
              <p className="font-body text-white/70 text-sm mt-3">
                Para:{' '}
                <span className="text-white font-semibold">{card.recipient_name}</span>
              </p>
            )}
            {card.customer_name && (
              <p className="font-body text-white/50 text-xs mt-1">
                De: {card.customer_name}
              </p>
            )}
          </div>

          {/* Footer strip */}
          <div className="bg-spa-mid/30 px-6 sm:px-8 py-3 flex items-center justify-between gap-4 border-t border-white/10">
            <div className="flex items-center gap-1.5">
              <Calendar size={13} className="text-white/50" />
              <span className="font-body text-xs text-white/60">
                Válido até {formatDate(card.valid_until)}
              </span>
            </div>
            {card.total && (
              <span className="font-display text-sm font-bold text-spa-accent">
                {formatCurrency(card.total)}
              </span>
            )}
          </div>
        </div>

        {/* Services */}
        {card.items?.length > 0 && (
          <div className="bg-white rounded-xl3 shadow-card p-6 mb-6">
            <h2 className="font-display text-lg text-spa-dark font-bold mb-4">
              Serviços incluídos
            </h2>
            <div className="space-y-3">
              {card.items.map((item, i) => (
                <div key={i} className="flex items-center justify-between py-2.5 border-b border-gray-100 last:border-0">
                  <div>
                    <p className="font-body text-sm font-medium text-spa-dark">{item.service_name}</p>
                    <p className="font-body text-xs text-spa-muted mt-0.5">
                      {item.service_category}
                      {item.service_duration ? ` • ${item.service_duration} min` : ''}
                    </p>
                  </div>
                  {item.quantity > 1 && (
                    <span className="font-body text-xs font-semibold text-spa-mid bg-spa-pale px-2.5 py-1 rounded-full">
                      ×{item.quantity}
                    </span>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Details */}
        <div className="bg-white rounded-xl3 shadow-card p-6 mb-8">
          <h2 className="font-display text-lg text-spa-dark font-bold mb-4">Detalhes</h2>
          <dl className="space-y-3">
            {[
              { label: 'Código', value: card.code },
              { label: 'Status', value: cfg.label },
              { label: 'Válido até', value: formatDate(card.valid_until) },
              card.redeemed_at && { label: 'Utilizado em', value: formatDatetime(card.redeemed_at) },
              { label: 'Emitido em', value: formatDate(card.created_at) },
              card.total && { label: 'Valor total', value: formatCurrency(card.total) },
            ].filter(Boolean).map(row => (
              <div key={row.label} className="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                <dt className="font-body text-xs text-spa-muted">{row.label}</dt>
                <dd className="font-body text-sm font-medium text-spa-dark">{row.value}</dd>
              </div>
            ))}
          </dl>
        </div>

        {/* Redeemed note */}
        {card.status === 'redeemed' && (
          <div className="bg-blue-50 border border-blue-200 rounded-xl3 p-5 mb-8">
            <p className="font-body text-sm text-blue-700 font-semibold mb-1">Cartão já utilizado</p>
            <p className="font-body text-xs text-blue-600 leading-relaxed">
              Este cartão foi utilizado em {formatDatetime(card.redeemed_at)}.
              Se tiver dúvidas, entre em contato conosco pelo WhatsApp.
            </p>
          </div>
        )}

        {/* CTA */}
        <div className="flex flex-col sm:flex-row gap-3">
          <Link to="/" className="flex-1 flex items-center justify-center gap-2 border border-spa-dark text-spa-dark font-body font-medium px-5 py-3.5 rounded-xl hover:bg-spa-pale transition-all text-sm">
            <ArrowLeft size={15} /> Início
          </Link>
          {isValid && (
            <a href="https://wa.me/5547991151707" target="_blank" rel="noopener noreferrer"
              className="flex-1 flex items-center justify-center gap-2 bg-green-500 text-white font-body font-medium px-5 py-3.5 rounded-xl hover:bg-green-600 transition-all text-sm">
              <MessageCircle size={15} /> Agendar pelo WhatsApp
            </a>
          )}
          <Link to="/servicos" className="flex-1 flex items-center justify-center gap-2 bg-spa-dark text-white font-body font-medium px-5 py-3.5 rounded-xl hover:bg-spa-mid transition-all text-sm">
            Ver Serviços
          </Link>
        </div>

      </div>
    </div>
  )
}
