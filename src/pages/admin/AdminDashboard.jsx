import { useEffect, useState } from 'react'
import { ShoppingBag, Gift, TrendingUp, DollarSign, Loader2, AlertCircle, RefreshCw } from 'lucide-react'
import { adminApi, ApiError } from '../../services/api'

function fmt(value) {
  return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function AdminDashboard() {
  const [data, setData]       = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState('')

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await adminApi.getDashboard()
      setData(res.data)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar dashboard.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [])

  if (loading) return <PageLoader />
  if (error)   return <PageError msg={error} onRetry={load} />

  const statuses = ['active', 'redeemed', 'expired', 'cancelled']
  const statusLabels = { active: 'Ativos', redeemed: 'Utilizados', expired: 'Expirados', cancelled: 'Cancelados' }
  const statusColors = { active: 'text-green-600', redeemed: 'text-blue-600', expired: 'text-gray-400', cancelled: 'text-red-400' }

  const giftCardMap = Object.fromEntries(
    (data?.gift_cards_by_status ?? []).map(s => [s.status, s.total])
  )

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="font-display text-2xl lg:text-3xl text-spa-dark font-bold">Dashboard</h1>
          <p className="text-sm text-spa-muted font-body mt-1">Resumo em tempo real</p>
        </div>
        <button onClick={load} className="flex items-center gap-2 text-sm text-spa-muted hover:text-spa-dark font-body px-3 py-2 rounded-xl hover:bg-gray-100 transition-all">
          <RefreshCw size={14} /> Atualizar
        </button>
      </div>

      {/* Stat cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <StatCard
          icon={ShoppingBag}
          label="Pedidos hoje"
          value={data?.orders_today ?? 0}
          color="bg-spa-pale text-spa-dark"
        />
        <StatCard
          icon={DollarSign}
          label="Receita hoje"
          value={`R$ ${fmt(data?.revenue_today)}`}
          color="bg-green-50 text-green-700"
        />
        <StatCard
          icon={TrendingUp}
          label="Receita no mês"
          value={`R$ ${fmt(data?.revenue_this_month)}`}
          color="bg-blue-50 text-blue-700"
        />
        <StatCard
          icon={Gift}
          label="Cartões ativos"
          value={data?.active_gift_cards ?? 0}
          color="bg-purple-50 text-purple-700"
        />
      </div>

      {/* Grid de detalhes */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Cartões por status */}
        <div className="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
          <h3 className="font-display text-base font-bold text-spa-dark mb-5">Cartões Presente</h3>
          <div className="space-y-4">
            {statuses.map(s => {
              const count = Number(giftCardMap[s] ?? 0)
              const total = statuses.reduce((acc, k) => acc + Number(giftCardMap[k] ?? 0), 0)
              const pct   = total > 0 ? Math.round((count / total) * 100) : 0
              return (
                <div key={s}>
                  <div className="flex justify-between text-sm font-body mb-1">
                    <span className="text-spa-muted">{statusLabels[s]}</span>
                    <span className={`font-semibold ${statusColors[s]}`}>{count}</span>
                  </div>
                  <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div
                      className={`h-full rounded-full transition-all duration-500 ${
                        s === 'active'   ? 'bg-green-400' :
                        s === 'redeemed' ? 'bg-blue-400'  :
                        s === 'expired'  ? 'bg-gray-300'  : 'bg-red-300'
                      }`}
                      style={{ width: `${pct}%` }}
                    />
                  </div>
                </div>
              )
            })}
          </div>
        </div>

        {/* Serviços por categoria */}
        <div className="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
          <h3 className="font-display text-base font-bold text-spa-dark mb-5">Serviços por Categoria</h3>
          {data?.services_by_category?.length ? (
            <div className="space-y-3">
              {data.services_by_category.map(cat => (
                <div key={cat.category} className="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                  <span className="text-sm font-body text-spa-muted">{cat.category}</span>
                  <span className="text-sm font-body font-semibold text-spa-dark">
                    {cat.total} serviço{Number(cat.total) !== 1 ? 's' : ''}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-spa-muted font-body">Nenhum serviço cadastrado.</p>
          )}
        </div>
      </div>
    </div>
  )
}

function StatCard({ icon: Icon, label, value, color }) {
  return (
    <div className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
      <div className={`w-10 h-10 rounded-xl flex items-center justify-center mb-3 ${color}`}>
        <Icon size={18} />
      </div>
      <p className="text-xs font-body text-spa-muted uppercase tracking-wider mb-1">{label}</p>
      <p className="font-display text-xl font-bold text-spa-dark">{value}</p>
    </div>
  )
}

function PageLoader() {
  return (
    <div className="flex items-center justify-center h-64">
      <Loader2 size={28} className="animate-spin text-spa-light" />
    </div>
  )
}

function PageError({ msg, onRetry }) {
  return (
    <div className="flex flex-col items-center justify-center h-64 gap-4 text-center">
      <AlertCircle size={32} className="text-red-400" />
      <p className="text-sm font-body text-spa-muted">{msg}</p>
      {onRetry && (
        <button onClick={onRetry} className="text-sm font-body text-spa-dark underline">Tentar novamente</button>
      )}
    </div>
  )
}
