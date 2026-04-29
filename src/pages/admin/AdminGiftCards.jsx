import { useEffect, useState } from 'react'
import { Search, QrCode, Download, CheckCircle, XCircle, Clock, Loader2, AlertCircle, RefreshCw, Check } from 'lucide-react'
import { adminApi, ApiError } from '../../services/api'

function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('pt-BR') : '—'
}

const STATUS = {
  active:   { label: 'Ativo',      icon: CheckCircle, color: 'text-green-600 bg-green-50' },
  redeemed: { label: 'Utilizado',  icon: Check,       color: 'text-blue-600 bg-blue-50' },
  expired:  { label: 'Expirado',   icon: Clock,       color: 'text-gray-400 bg-gray-100' },
  cancelled:{ label: 'Cancelado',  icon: XCircle,     color: 'text-red-500 bg-red-50' },
}

export default function AdminGiftCards() {
  const [cards, setCards]       = useState([])
  const [loading, setLoading]   = useState(true)
  const [error, setError]       = useState('')
  const [toast, setToast]       = useState('')
  const [redeemModal, setRedeemModal] = useState(null)
  const [redeemForm, setRedeemForm]   = useState({ redeemed_by: '', notes: '' })
  const [redeeming, setRedeeming]     = useState(false)
  const [filters, setFilters]   = useState({ status: '', code: '', customer_name: '' })

  const showToast = (msg) => { setToast(msg); setTimeout(() => setToast(''), 3000) }

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v))
      const res = await adminApi.getGiftCards(params)
      setCards(res.data?.gift_cards ?? [])
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar cartões.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [])

  const handleRedeem = async () => {
    if (!redeemForm.redeemed_by.trim()) { showToast('Informe quem está utilizando.'); return }
    setRedeeming(true)
    try {
      await adminApi.redeemGiftCard(redeemModal.code, redeemForm)
      showToast('Cartão marcado como utilizado.')
      setRedeemModal(null)
      setRedeemForm({ redeemed_by: '', notes: '' })
      await load()
    } catch (err) {
      showToast('Erro: ' + err.message)
    } finally {
      setRedeeming(false)
    }
  }

  return (
    <div>
      {toast && (
        <div className="fixed top-6 right-6 z-50 flex items-center gap-2 bg-spa-dark text-white text-sm font-body px-4 py-3 rounded-xl shadow-lg">
          <Check size={14} /> {toast}
        </div>
      )}

      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl lg:text-3xl text-spa-dark font-bold">Cartões Presente</h1>
          <p className="text-sm text-spa-muted font-body mt-1">{cards.length} cartão(ns) exibido(s)</p>
        </div>
        <button onClick={load} className="flex items-center gap-2 text-sm text-spa-muted hover:text-spa-dark font-body px-3 py-2 rounded-xl hover:bg-gray-100 transition-all">
          <RefreshCw size={14} /> Atualizar
        </button>
      </div>

      {/* Filtros */}
      <div className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 mb-6">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div className="relative">
            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input className="input-field pl-9 text-sm font-mono tracking-wider" placeholder="SPA-XXXXXX"
              value={filters.code}
              onChange={e => setFilters(f => ({ ...f, code: e.target.value.toUpperCase() }))} />
          </div>
          <input className="input-field text-sm" placeholder="Nome do cliente"
            value={filters.customer_name}
            onChange={e => setFilters(f => ({ ...f, customer_name: e.target.value }))} />
          <select className="input-field text-sm" value={filters.status}
            onChange={e => setFilters(f => ({ ...f, status: e.target.value }))}>
            <option value="">Todos os status</option>
            {Object.entries(STATUS).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
          </select>
        </div>
        <div className="flex gap-2 mt-4">
          <button onClick={load}
            className="text-sm font-body font-medium bg-spa-dark text-white px-4 py-2 rounded-xl hover:bg-spa-mid transition-all">
            Buscar
          </button>
          <button onClick={() => { setFilters({ status: '', code: '', customer_name: '' }); load() }}
            className="text-sm font-body text-spa-muted px-4 py-2 rounded-xl hover:bg-gray-100 transition-all">
            Limpar
          </button>
        </div>
      </div>

      {/* Lista */}
      {loading ? (
        <div className="flex justify-center py-16"><Loader2 size={28} className="animate-spin text-spa-light" /></div>
      ) : error ? (
        <div className="flex items-center gap-2 p-4 bg-red-50 rounded-xl text-red-700 text-sm font-body">
          <AlertCircle size={16} /> {error}
        </div>
      ) : cards.length === 0 ? (
        <div className="text-center py-16">
          <QrCode size={40} className="text-gray-300 mx-auto mb-3" />
          <p className="text-sm text-spa-muted font-body">Nenhum cartão encontrado.</p>
        </div>
      ) : (
        <div className="space-y-4">
          {cards.map(card => {
            const s = STATUS[card.status] ?? STATUS.expired
            const Icon = s.icon
            const isActive  = card.status === 'active'
            const isExpired = new Date(card.valid_until) < new Date()

            return (
              <div key={card.id} className="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                <div className="flex flex-wrap items-start justify-between gap-4">
                  {/* Código + status */}
                  <div>
                    <div className="flex items-center gap-3 mb-2">
                      <span className="font-display text-xl font-bold text-spa-dark tracking-widest font-mono">
                        {card.code}
                      </span>
                      <span className={`flex items-center gap-1.5 text-xs font-body font-medium px-2.5 py-1 rounded-full ${s.color}`}>
                        <Icon size={11} /> {s.label}
                      </span>
                    </div>
                    <p className="text-sm font-body text-spa-muted">
                      Cliente: <span className="text-spa-dark font-medium">{card.customer_name}</span>
                      {' • '}Válido até: <span className={`font-medium ${isExpired && isActive ? 'text-red-500' : 'text-spa-dark'}`}>{fmtDate(card.valid_until)}</span>
                    </p>
                    {card.redeemed_at && (
                      <p className="text-xs font-body text-blue-600 mt-1">
                        Utilizado em {fmtDate(card.redeemed_at)} por {card.redeemed_by ?? '—'}
                      </p>
                    )}
                    {card.redemption_notes && (
                      <p className="text-xs font-body text-spa-muted mt-1 bg-gray-50 rounded-lg px-3 py-2 border border-gray-100">
                        <span className="font-semibold text-spa-dark">Obs:</span> {card.redemption_notes}
                      </p>
                    )}
                  </div>

                  {/* Ações */}
                  <div className="flex items-center gap-2 shrink-0">
                    {card.pdf_url && (
                      <a href={card.pdf_url} target="_blank" rel="noopener noreferrer"
                        className="flex items-center gap-1.5 text-xs font-body text-spa-muted hover:text-spa-dark px-3 py-2 rounded-xl hover:bg-gray-100 transition-all">
                        <Download size={13} /> PDF
                      </a>
                    )}
                    {isActive && !isExpired && (
                      <button
                        onClick={() => { setRedeemModal(card); setRedeemForm({ redeemed_by: '', notes: '' }) }}
                        className="flex items-center gap-1.5 text-xs font-body font-medium bg-spa-dark text-white px-3 py-2 rounded-xl hover:bg-spa-mid transition-all">
                        <CheckCircle size={13} /> Utilizar
                      </button>
                    )}
                  </div>
                </div>

                {/* Serviços */}
                {card.items?.length > 0 && (
                  <div className="mt-4 pt-4 border-t border-gray-100">
                    <p className="text-xs font-body font-semibold text-spa-muted uppercase tracking-wider mb-2">Serviços</p>
                    <div className="flex flex-wrap gap-2">
                      {card.items.map((item, i) => (
                        <span key={i} className="text-xs font-body bg-spa-pale text-spa-dark px-3 py-1.5 rounded-full">
                          {item.service_name} x{item.quantity}
                        </span>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )
          })}
        </div>
      )}

      {/* Modal de utilização */}
      {redeemModal && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl w-full max-w-md">
            <div className="p-6 border-b border-gray-100">
              <h2 className="font-display text-xl text-spa-dark font-bold">Utilizar Cartão</h2>
              <p className="text-sm text-spa-muted font-body mt-1">
                Código: <strong className="font-mono text-spa-dark">{redeemModal.code}</strong>
              </p>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="block text-xs font-body font-semibold text-spa-muted mb-1.5">
                  Utilizado por *
                </label>
                <input className="input-field" placeholder="Nome da pessoa que utilizará"
                  value={redeemForm.redeemed_by}
                  onChange={e => setRedeemForm(f => ({ ...f, redeemed_by: e.target.value }))} />
              </div>
              <div>
                <label className="block text-xs font-body font-semibold text-spa-muted mb-1.5">
                  Observações (opcional)
                </label>
                <textarea className="input-field resize-none" rows={3}
                  placeholder="Alguma observação sobre o uso..."
                  value={redeemForm.notes}
                  onChange={e => setRedeemForm(f => ({ ...f, notes: e.target.value }))} />
              </div>
              <div className="flex gap-3 pt-2">
                <button onClick={() => setRedeemModal(null)}
                  className="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-body text-spa-muted hover:bg-gray-50 transition-all">
                  Cancelar
                </button>
                <button onClick={handleRedeem} disabled={redeeming}
                  className="flex-1 flex items-center justify-center gap-2 py-3 bg-spa-dark text-white text-sm font-body font-medium rounded-xl hover:bg-spa-mid transition-all disabled:opacity-60">
                  {redeeming ? <Loader2 size={14} className="animate-spin" /> : <CheckCircle size={14} />}
                  {redeeming ? 'Salvando...' : 'Confirmar uso'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
