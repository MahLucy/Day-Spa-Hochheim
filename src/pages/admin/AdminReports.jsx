import { useEffect, useState } from 'react'
import { TrendingUp, Package, Loader2, AlertCircle, RefreshCw } from 'lucide-react'
import { adminApi, ApiError } from '../../services/api'

function fmtMoney(v) {
  return `R$ ${Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
}

function fmtMonth(dateStr) {
  if (!dateStr) return '—'
  const [y, m] = String(dateStr).split('-')
  const months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez']
  return `${months[parseInt(m, 10) - 1]}/${y.slice(2)}`
}

export default function AdminReports() {
  const [revenue, setRevenue]   = useState([])
  const [services, setServices] = useState([])
  const [loading, setLoading]   = useState(true)
  const [error, setError]       = useState('')
  const [months, setMonths]     = useState(6)

  const load = async (m = months) => {
    setLoading(true)
    setError('')
    try {
      const [revRes, svcRes] = await Promise.all([
        adminApi.getRevenueReport(m),
        adminApi.getServicesReport(10),
      ])
      setRevenue(revRes.data?.revenue ?? [])
      setServices(svcRes.data?.services ?? [])
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar relatórios.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load(months) }, [months])

  const maxRevenue  = revenue.reduce((max, r) => Math.max(max, Number(r.total || 0)), 0)
  const maxServices = services.reduce((max, s) => Math.max(max, Number(s.total_sold || 0)), 0)
  const totalRevenue = revenue.reduce((sum, r) => sum + Number(r.total || 0), 0)

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="font-display text-2xl lg:text-3xl text-spa-dark font-bold">Relatórios</h1>
          <p className="text-sm text-spa-muted font-body mt-1">Análise de vendas e desempenho</p>
        </div>
        <div className="flex items-center gap-3">
          <select
            className="input-field text-sm"
            value={months}
            onChange={e => setMonths(Number(e.target.value))}>
            <option value={3}>Últimos 3 meses</option>
            <option value={6}>Últimos 6 meses</option>
            <option value={12}>Últimos 12 meses</option>
          </select>
          <button onClick={() => load(months)}
            className="flex items-center gap-2 text-sm text-spa-muted hover:text-spa-dark font-body px-3 py-2 rounded-xl hover:bg-gray-100 transition-all">
            <RefreshCw size={14} /> Atualizar
          </button>
        </div>
      </div>

      {loading ? (
        <div className="flex justify-center py-16">
          <Loader2 size={28} className="animate-spin text-spa-light" />
        </div>
      ) : error ? (
        <div className="flex items-center gap-2 p-4 bg-red-50 rounded-xl text-red-700 text-sm font-body">
          <AlertCircle size={16} /> {error}
        </div>
      ) : (
        <div className="space-y-6">

          {/* ── Receita por Mês ─────────────────────────────────────── */}
          <div className="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <div className="flex items-center gap-3 mb-6">
              <div className="w-9 h-9 bg-blue-50 rounded-xl flex items-center justify-center shrink-0">
                <TrendingUp size={16} className="text-blue-600" />
              </div>
              <div>
                <h2 className="font-display text-base font-bold text-spa-dark">Receita por Mês</h2>
                <p className="text-xs font-body text-spa-muted">Pedidos com pagamento aprovado</p>
              </div>
            </div>

            {revenue.length === 0 ? (
              <p className="text-sm text-spa-muted font-body text-center py-10">
                Nenhuma receita registrada no período.
              </p>
            ) : (
              <>
                <div className="space-y-3">
                  {revenue.map((r, i) => {
                    const val = Number(r.total || 0)
                    const pct = maxRevenue > 0 ? (val / maxRevenue) * 100 : 0
                    return (
                      <div key={i} className="flex items-center gap-3">
                        <span className="text-xs font-body text-spa-muted w-14 shrink-0 text-right">
                          {fmtMonth(r.month)}
                        </span>
                        <div className="flex-1 h-8 bg-gray-100 rounded-lg overflow-hidden">
                          <div
                            className="h-full bg-blue-400 rounded-lg transition-all duration-700 flex items-center justify-end pr-2"
                            style={{ width: `${Math.max(pct, 1)}%` }}
                          >
                            {pct > 25 && (
                              <span className="text-xs font-body font-medium text-white">
                                {fmtMoney(val)}
                              </span>
                            )}
                          </div>
                        </div>
                        <span className="text-xs font-body font-semibold text-spa-dark w-28 text-right shrink-0">
                          {fmtMoney(val)}
                        </span>
                      </div>
                    )
                  })}
                </div>

                {/* Totalizador */}
                <div className="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between">
                  <span className="text-sm font-body text-spa-muted">
                    Total em {months} {months === 1 ? 'mês' : 'meses'}
                  </span>
                  <span className="font-display text-2xl font-bold text-spa-dark">
                    {fmtMoney(totalRevenue)}
                  </span>
                </div>
              </>
            )}
          </div>

          {/* ── Top Serviços ─────────────────────────────────────────── */}
          <div className="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <div className="flex items-center gap-3 mb-6">
              <div className="w-9 h-9 bg-purple-50 rounded-xl flex items-center justify-center shrink-0">
                <Package size={16} className="text-purple-600" />
              </div>
              <div>
                <h2 className="font-display text-base font-bold text-spa-dark">Serviços Mais Vendidos</h2>
                <p className="text-xs font-body text-spa-muted">Top 10 por quantidade total vendida</p>
              </div>
            </div>

            {services.length === 0 ? (
              <p className="text-sm text-spa-muted font-body text-center py-10">
                Nenhum serviço vendido ainda.
              </p>
            ) : (
              <div className="space-y-4">
                {services.map((svc, i) => {
                  const qty = Number(svc.total_sold || 0)
                  const rev = Number(svc.revenue || 0)
                  const pct = maxServices > 0 ? (qty / maxServices) * 100 : 0
                  return (
                    <div key={i} className="flex items-center gap-3">
                      {/* Posição */}
                      <span className={`text-xs font-body font-bold w-6 text-center shrink-0 ${
                        i === 0 ? 'text-yellow-500' :
                        i === 1 ? 'text-gray-400' :
                        i === 2 ? 'text-amber-600' : 'text-spa-muted'
                      }`}>
                        {i + 1}
                      </span>

                      {/* Barra + nome */}
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between mb-1.5">
                          <span className="text-xs font-body font-medium text-spa-dark truncate pr-2">
                            {svc.name}
                          </span>
                          <span className="text-xs font-body text-spa-muted shrink-0">
                            {qty} vendido{qty !== 1 ? 's' : ''}
                          </span>
                        </div>
                        <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                          <div
                            className="h-full bg-purple-400 rounded-full transition-all duration-700"
                            style={{ width: `${Math.max(pct, 1)}%` }}
                          />
                        </div>
                      </div>

                      {/* Receita */}
                      <span className="text-xs font-body font-semibold text-spa-dark w-28 text-right shrink-0">
                        {fmtMoney(rev)}
                      </span>
                    </div>
                  )
                })}
              </div>
            )}
          </div>

        </div>
      )}
    </div>
  )
}
