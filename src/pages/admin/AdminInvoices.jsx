import { useEffect, useState } from 'react'
import { FileText, Download, RefreshCw, Loader2, AlertCircle, Check } from 'lucide-react'
import { adminApi, ApiError } from '../../services/api'

function fmtDate(d) {
  return d ? new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—'
}

const STATUS = {
  pending:   { label: 'Pendente',   color: 'bg-yellow-50 text-yellow-700' },
  issued:    { label: 'Emitida',    color: 'bg-green-50 text-green-700' },
  cancelled: { label: 'Cancelada',  color: 'bg-gray-100 text-gray-500' },
  error:     { label: 'Erro',       color: 'bg-red-50 text-red-600' },
}

export default function AdminInvoices() {
  const [invoices, setInvoices] = useState([])
  const [loading, setLoading]  = useState(true)
  const [error, setError]      = useState('')
  const [toast, setToast]      = useState('')
  const [reissuing, setReissuing]   = useState(null)
  const [consulting, setConsulting] = useState(null)
  const [filters, setFilters]  = useState({ status: '' })

  const showToast = (msg) => { setToast(msg); setTimeout(() => setToast(''), 3000) }

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v))
      const res = await adminApi.getInvoices(params)
      setInvoices(res.data?.invoices ?? [])
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar notas fiscais.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [])

  const handleConsultStatus = async (invoice) => {
    setConsulting(invoice.id)
    try {
      const res = await adminApi.consultInvoiceStatus(invoice.id)
      showToast(`Status: ${res.data?.status ?? 'consultado'}`)
      await load()
    } catch (err) {
      showToast('Erro: ' + err.message)
    } finally {
      setConsulting(null)
    }
  }

  const handleReissue = async (invoice) => {
    if (!confirm(`Reemitir nota fiscal do pedido #${invoice.order_id}?`)) return
    setReissuing(invoice.id)
    try {
      await adminApi.reissueInvoice(invoice.id)
      showToast('Nota fiscal reenviada para emissão.')
      await load()
    } catch (err) {
      showToast('Erro: ' + err.message)
    } finally {
      setReissuing(null)
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
          <h1 className="font-display text-2xl lg:text-3xl text-spa-dark font-bold">Notas Fiscais</h1>
          <p className="text-sm text-spa-muted font-body mt-1">{invoices.length} nota(s) exibida(s)</p>
        </div>
        <button onClick={load} className="flex items-center gap-2 text-sm text-spa-muted hover:text-spa-dark font-body px-3 py-2 rounded-xl hover:bg-gray-100 transition-all">
          <RefreshCw size={14} /> Atualizar
        </button>
      </div>

      {/* Filtros */}
      <div className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 mb-6 flex gap-4 flex-wrap">
        <select className="input-field text-sm flex-1 min-w-[160px]" value={filters.status}
          onChange={e => setFilters(f => ({ ...f, status: e.target.value }))}>
          <option value="">Todos os status</option>
          {Object.entries(STATUS).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
        </select>
        <button onClick={load}
          className="text-sm font-body font-medium bg-spa-dark text-white px-4 py-2 rounded-xl hover:bg-spa-mid transition-all">
          Filtrar
        </button>
      </div>

      {/* Tabela */}
      {loading ? (
        <div className="flex justify-center py-16"><Loader2 size={28} className="animate-spin text-spa-light" /></div>
      ) : error ? (
        <div className="flex items-center gap-2 p-4 bg-red-50 rounded-xl text-red-700 text-sm font-body">
          <AlertCircle size={16} /> {error}
        </div>
      ) : invoices.length === 0 ? (
        <div className="text-center py-16">
          <FileText size={40} className="text-gray-300 mx-auto mb-3" />
          <p className="text-sm text-spa-muted font-body">Nenhuma nota fiscal encontrada.</p>
        </div>
      ) : (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-gray-100">
                  {['#', 'Pedido', 'Cliente', 'N° Nota', 'Status', 'Emissão', 'Ações'].map(h => (
                    <th key={h} className="text-left text-xs font-body font-semibold text-spa-muted uppercase tracking-wider px-5 py-4">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {invoices.map(inv => {
                  const s = STATUS[inv.status] ?? STATUS.error
                  return (
                    <tr key={inv.id} className="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                      <td className="px-5 py-4 text-sm font-body text-spa-muted">{inv.id}</td>
                      <td className="px-5 py-4 text-sm font-body font-medium text-spa-dark">#{inv.order_id}</td>
                      <td className="px-5 py-4 text-sm font-body text-spa-muted">{inv.customer_name ?? '—'}</td>
                      <td className="px-5 py-4 text-sm font-body text-spa-dark font-mono">
                        {inv.invoice_number ?? '—'}
                      </td>
                      <td className="px-5 py-4">
                        <span className={`text-xs font-body font-medium px-2.5 py-1 rounded-full ${s.color}`}>{s.label}</span>
                        {inv.status === 'error' && inv.error_message && (
                          <p className="text-xs text-red-500 font-body mt-1 max-w-[160px] truncate" title={inv.error_message}>
                            {inv.error_message}
                          </p>
                        )}
                      </td>
                      <td className="px-5 py-4 text-xs font-body text-spa-muted">{fmtDate(inv.issued_at)}</td>
                      <td className="px-5 py-4">
                        <div className="flex items-center gap-2 flex-wrap">
                          {(inv.url_danfse || inv.pdf_url) && (
                            <a href={inv.url_danfse ?? inv.pdf_url} target="_blank" rel="noopener noreferrer"
                              className="flex items-center gap-1 text-xs font-body text-spa-muted hover:text-spa-dark px-2 py-1 rounded-lg hover:bg-gray-100 transition-all">
                              <Download size={12} /> DANFSe
                            </a>
                          )}
                          {inv.status === 'pending' && inv.focusnfe_ref && (
                            <button onClick={() => handleConsultStatus(inv)} disabled={consulting === inv.id}
                              className="flex items-center gap-1 text-xs font-body text-blue-600 hover:text-blue-800 px-2 py-1 rounded-lg hover:bg-blue-50 transition-all disabled:opacity-50">
                              {consulting === inv.id ? <Loader2 size={12} className="animate-spin" /> : <RefreshCw size={12} />}
                              Consultar
                            </button>
                          )}
                          {(inv.status === 'error' || inv.status === 'pending') && (
                            <button onClick={() => handleReissue(inv)} disabled={reissuing === inv.id}
                              className="flex items-center gap-1 text-xs font-body font-medium text-spa-mid hover:text-spa-dark px-2 py-1 rounded-lg hover:bg-spa-pale transition-all disabled:opacity-50">
                              {reissuing === inv.id ? <Loader2 size={12} className="animate-spin" /> : <RefreshCw size={12} />}
                              Reemitir
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  )
}
