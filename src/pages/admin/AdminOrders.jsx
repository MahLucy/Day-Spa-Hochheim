import { useEffect, useState } from 'react'
import { Search, Mail, ChevronDown, ChevronUp, Loader2, AlertCircle, RefreshCw, Check, Clock } from 'lucide-react'
import { adminApi, ApiError } from '../../services/api'

const STATUS_LABELS = {
  pending:    { label: 'Pendente',    color: 'bg-yellow-50 text-yellow-700' },
  paid:       { label: 'Pago',        color: 'bg-green-50 text-green-700' },
  processing: { label: 'Processando', color: 'bg-blue-50 text-blue-700' },
  completed:  { label: 'Concluído',   color: 'bg-spa-pale text-spa-dark' },
  cancelled:  { label: 'Cancelado',   color: 'bg-gray-100 text-gray-500' },
  refunded:   { label: 'Estornado',   color: 'bg-red-50 text-red-600' },
}

function fmtDate(d) {
  return d ? new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—'
}

function fmtMoney(v) {
  return `R$ ${Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
}

export default function AdminOrders() {
  const [orders, setOrders]    = useState([])
  const [total, setTotal]      = useState(0)
  const [page, setPage]        = useState(1)
  const [loading, setLoading]  = useState(true)
  const [error, setError]      = useState('')
  const [expanded, setExpanded] = useState(null)
  const [detail, setDetail]    = useState({})
  const [toast, setToast]      = useState('')
  const [resending, setResending] = useState(null)
  const [expiring, setExpiring]   = useState(false)

  const [filters, setFilters]  = useState({ status: '', date_from: '', date_to: '', customer_name: '', recipient_name: '' })

  const showToast = (msg) => { setToast(msg); setTimeout(() => setToast(''), 3000) }

  const load = async (p = page) => {
    setLoading(true)
    setError('')
    try {
      const params = { page: p, limit: 20, ...Object.fromEntries(Object.entries(filters).filter(([,v]) => v)) }
      const res = await adminApi.getOrders(params)
      setOrders(res.data?.orders ?? [])
      setTotal(res.data?.total ?? 0)
      setPage(p)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar pedidos.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load(1) }, [])

  const toggleExpand = async (order) => {
    if (expanded === order.id) { setExpanded(null); return }
    setExpanded(order.id)
    if (!detail[order.id]) {
      try {
        const res = await adminApi.getOrder(order.id)
        setDetail(d => ({ ...d, [order.id]: res.data }))
      } catch { /* ignora */ }
    }
  }

  const handleResend = async (orderId) => {
    setResending(orderId)
    try {
      await adminApi.resendEmail(orderId)
      showToast('E-mail reenviado com sucesso.')
    } catch (err) {
      showToast('Erro: ' + err.message)
    } finally {
      setResending(null)
    }
  }

  const handleExpirePending = async () => {
    if (!window.confirm('Cancelar todos os pedidos pendentes com mais de 24 horas?')) return
    setExpiring(true)
    try {
      const res = await adminApi.expirePendingOrders()
      const count = res.data?.cancelled_count ?? 0
      showToast(count > 0 ? `${count} pedido(s) cancelado(s).` : 'Nenhum pedido expirado encontrado.')
      if (count > 0) load(1)
    } catch (err) {
      showToast('Erro: ' + err.message)
    } finally {
      setExpiring(false)
    }
  }

  const lastPage = Math.ceil(total / 20)

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
          <h1 className="font-display text-2xl lg:text-3xl text-spa-dark font-bold">Pedidos</h1>
          <p className="text-sm text-spa-muted font-body mt-1">{total} pedido{total !== 1 ? 's' : ''} encontrado{total !== 1 ? 's' : ''}</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={handleExpirePending} disabled={expiring}
            className="flex items-center gap-2 text-sm text-red-500 hover:text-red-700 font-body px-3 py-2 rounded-xl hover:bg-red-50 transition-all disabled:opacity-50">
            {expiring ? <Loader2 size={14} className="animate-spin" /> : <Clock size={14} />}
            Cancelar expirados
          </button>
          <button onClick={() => load(1)} className="flex items-center gap-2 text-sm text-spa-muted hover:text-spa-dark font-body px-3 py-2 rounded-xl hover:bg-gray-100 transition-all">
            <RefreshCw size={14} /> Atualizar
          </button>
        </div>
      </div>

      {/* Filtros */}
      <div className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 mb-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div className="relative">
            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input className="input-field pl-9 text-sm" placeholder="Nome do comprador"
              value={filters.customer_name}
              onChange={e => setFilters(f => ({ ...f, customer_name: e.target.value }))} />
          </div>
          <div className="relative">
            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input className="input-field pl-9 text-sm" placeholder="Nome do presenteado"
              value={filters.recipient_name}
              onChange={e => setFilters(f => ({ ...f, recipient_name: e.target.value }))} />
          </div>
          <select className="input-field text-sm" value={filters.status}
            onChange={e => setFilters(f => ({ ...f, status: e.target.value }))}>
            <option value="">Todos os status</option>
            {Object.entries(STATUS_LABELS).map(([k, v]) => (
              <option key={k} value={k}>{v.label}</option>
            ))}
          </select>
          <input type="date" className="input-field text-sm" value={filters.date_from}
            onChange={e => setFilters(f => ({ ...f, date_from: e.target.value }))} />
          <input type="date" className="input-field text-sm" value={filters.date_to}
            onChange={e => setFilters(f => ({ ...f, date_to: e.target.value }))} />
        </div>
        <div className="flex gap-2 mt-4">
          <button onClick={() => load(1)}
            className="text-sm font-body font-medium bg-spa-dark text-white px-4 py-2 rounded-xl hover:bg-spa-mid transition-all">
            Filtrar
          </button>
          <button onClick={() => { setFilters({ status: '', date_from: '', date_to: '', customer_name: '', recipient_name: '' }); load(1) }}
            className="text-sm font-body text-spa-muted px-4 py-2 rounded-xl hover:bg-gray-100 transition-all">
            Limpar
          </button>
        </div>
      </div>

      {/* Tabela */}
      {loading ? (
        <div className="flex justify-center py-16"><Loader2 size={28} className="animate-spin text-spa-light" /></div>
      ) : error ? (
        <div className="flex items-center gap-2 p-4 bg-red-50 rounded-xl text-red-700 text-sm font-body">
          <AlertCircle size={16} /> {error}
        </div>
      ) : (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          {orders.length === 0 ? (
            <p className="text-center text-sm text-spa-muted font-body py-12">Nenhum pedido encontrado.</p>
          ) : (
            orders.map(order => {
              const status = STATUS_LABELS[order.status] ?? { label: order.status, color: 'bg-gray-100 text-gray-600' }
              const isExpanded = expanded === order.id
              const d = detail[order.id]

              return (
                <div key={order.id} className="border-b border-gray-50 last:border-0">
                  {/* Linha principal */}
                  <div className="flex items-center gap-4 px-6 py-4 hover:bg-gray-50/50 transition-colors">
                    <div className="flex-1 grid grid-cols-1 sm:grid-cols-4 gap-2">
                      <div>
                        <p className="text-xs text-spa-muted font-body">#{order.id}</p>
                        <p className="text-sm font-body font-medium text-spa-dark">{order.customer_name}</p>
                        <p className="text-xs text-spa-muted font-body">{order.customer_email}</p>
                      </div>
                      <div className="sm:text-center">
                        <p className="text-xs text-spa-muted font-body mb-1">Status</p>
                        <span className={`text-xs font-body font-medium px-2.5 py-1 rounded-full ${status.color}`}>
                          {status.label}
                        </span>
                      </div>
                      <div className="sm:text-center">
                        <p className="text-xs text-spa-muted font-body mb-1">Total</p>
                        <p className="text-sm font-body font-semibold text-spa-dark">{fmtMoney(order.total)}</p>
                      </div>
                      <div className="sm:text-right">
                        <p className="text-xs text-spa-muted font-body mb-1">Data</p>
                        <p className="text-xs font-body text-spa-muted">{fmtDate(order.created_at)}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                      <button onClick={() => handleResend(order.id)} disabled={resending === order.id}
                        title="Reenviar e-mail"
                        className="p-2 rounded-lg hover:bg-spa-pale transition-all disabled:opacity-50">
                        {resending === order.id ? <Loader2 size={14} className="animate-spin text-spa-muted" /> : <Mail size={14} className="text-spa-muted" />}
                      </button>
                      <button onClick={() => toggleExpand(order)} className="p-2 rounded-lg hover:bg-gray-100 transition-all">
                        {isExpanded ? <ChevronUp size={14} className="text-spa-muted" /> : <ChevronDown size={14} className="text-spa-muted" />}
                      </button>
                    </div>
                  </div>

                  {/* Detalhe expandido */}
                  {isExpanded && (
                    <div className="px-6 pb-5 bg-gray-50/50 border-t border-gray-100">
                      {!d ? (
                        <div className="flex items-center gap-2 py-4 text-sm text-spa-muted font-body">
                          <Loader2 size={14} className="animate-spin" /> Carregando detalhes...
                        </div>
                      ) : (
                        <div className="pt-4 grid grid-cols-1 sm:grid-cols-2 gap-6">
                          <div>
                            <p className="text-xs font-body font-semibold text-spa-muted uppercase tracking-wider mb-3">Itens do pedido</p>
                            <div className="space-y-2">
                              {(d.items ?? []).map((item, i) => (
                                <div key={i} className="flex justify-between text-sm font-body">
                                  <span className="text-spa-dark">{item.service_snapshot?.name ?? item.service_name_current} x{item.quantity}</span>
                                  <span className="text-spa-muted">{fmtMoney(item.subtotal)}</span>
                                </div>
                              ))}
                            </div>
                          </div>
                          <div>
                            <p className="text-xs font-body font-semibold text-spa-muted uppercase tracking-wider mb-3">Dados do comprador</p>
                            <div className="space-y-1 text-sm font-body">
                              <p><span className="text-spa-muted">CPF:</span> <span className="text-spa-dark">{d.customer_cpf ?? '—'}</span></p>
                              <p><span className="text-spa-muted">Telefone:</span> <span className="text-spa-dark">{d.customer_phone ?? '—'}</span></p>
                              <p><span className="text-spa-muted">Formato:</span> <span className="text-spa-dark capitalize">{d.gift_card_format ?? '—'}</span></p>
                            </div>
                          </div>

                          {d.recipient_name && (
                            <div className="sm:col-span-2">
                              <p className="text-xs font-body font-semibold text-spa-muted uppercase tracking-wider mb-3">Presenteado</p>
                              <div className="flex flex-wrap gap-4 text-sm font-body p-3 bg-spa-pale rounded-xl border border-spa-accent/20">
                                <p><span className="text-spa-muted">Nome:</span> <span className="text-spa-dark font-medium">{d.recipient_name}</span></p>
                                {d.recipient_email && <p><span className="text-spa-muted">E-mail:</span> <span className="text-spa-dark">{d.recipient_email}</span></p>}
                                {d.recipient_phone && <p><span className="text-spa-muted">Telefone:</span> <span className="text-spa-dark">{d.recipient_phone}</span></p>}
                              </div>
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )
            })
          )}
        </div>
      )}

      {/* Paginação */}
      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2 mt-6">
          <button onClick={() => load(page - 1)} disabled={page === 1 || loading}
            className="px-4 py-2 text-sm font-body rounded-xl border border-gray-200 hover:bg-gray-50 disabled:opacity-50 transition-all">
            Anterior
          </button>
          <span className="text-sm font-body text-spa-muted">{page} / {lastPage}</span>
          <button onClick={() => load(page + 1)} disabled={page >= lastPage || loading}
            className="px-4 py-2 text-sm font-body rounded-xl border border-gray-200 hover:bg-gray-50 disabled:opacity-50 transition-all">
            Próxima
          </button>
        </div>
      )}
    </div>
  )
}
