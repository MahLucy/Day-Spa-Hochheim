import { useState, useEffect } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard, Package, ShoppingBag, Gift,
  FileText, BarChart2, LogOut, Menu, X,
} from 'lucide-react'

const navItems = [
  { to: '/admin',           label: 'Dashboard',     icon: LayoutDashboard, end: true },
  { to: '/admin/servicos',  label: 'Serviços',      icon: Package },
  { to: '/admin/pedidos',   label: 'Pedidos',       icon: ShoppingBag },
  { to: '/admin/cartoes',   label: 'Cartões',       icon: Gift },
  { to: '/admin/notas',     label: 'Notas Fiscais', icon: FileText },
  { to: '/admin/relatorios',label: 'Relatórios',    icon: BarChart2 },
]

export default function AdminLayout({ onLogout }) {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const navigate = useNavigate()

  // Fecha sidebar em mobile ao navegar
  const handleNav = () => setSidebarOpen(false)

  // Listener de logout forçado (401 da API)
  useEffect(() => {
    const handler = () => {
      onLogout()
      navigate('/admin', { replace: true })
    }
    window.addEventListener('admin:logout', handler)
    return () => window.removeEventListener('admin:logout', handler)
  }, [onLogout, navigate])

  const handleLogout = () => {
    sessionStorage.removeItem('adminCredentials')
    onLogout()
  }

  return (
    <div className="min-h-screen bg-gray-50 flex">
      {/* Overlay mobile */}
      {sidebarOpen && (
        <div className="fixed inset-0 bg-black/50 z-20 lg:hidden" onClick={() => setSidebarOpen(false)} />
      )}

      {/* Sidebar */}
      <aside className={`fixed top-0 left-0 h-full w-64 bg-spa-dark z-30 flex flex-col transition-transform duration-300 ${
        sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      }`}>
        {/* Logo */}
        <div className="p-6 border-b border-white/10">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 bg-spa-accent/20 rounded-xl flex items-center justify-center">
              <span className="text-spa-accent font-display font-bold text-sm">DS</span>
            </div>
            <div>
              <p className="text-white font-display font-bold text-sm leading-none">Day Spa Hochheim</p>
              <p className="text-white/40 font-body text-xs mt-0.5">Painel Admin</p>
            </div>
          </div>
        </div>

        {/* Nav */}
        <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
          {navItems.map(item => {
            const Icon = item.icon
            return (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                onClick={handleNav}
                className={({ isActive }) =>
                  `flex items-center gap-3 px-4 py-3 rounded-xl transition-all text-sm font-body font-medium ${
                    isActive
                      ? 'bg-spa-accent/20 text-spa-accent'
                      : 'text-white/60 hover:text-white hover:bg-white/5'
                  }`
                }
              >
                <Icon size={16} />
                {item.label}
              </NavLink>
            )
          })}
        </nav>

        {/* Logout */}
        <div className="p-4 border-t border-white/10">
          <button onClick={handleLogout}
            className="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-body font-medium text-white/40 hover:text-red-400 hover:bg-red-500/10 transition-all w-full">
            <LogOut size={16} />
            Sair
          </button>
        </div>
      </aside>

      {/* Main content */}
      <div className="flex-1 lg:ml-64 flex flex-col min-h-screen">
        {/* Top bar (mobile) */}
        <header className="lg:hidden flex items-center justify-between px-4 py-3 bg-white border-b border-gray-200 sticky top-0 z-10">
          <button onClick={() => setSidebarOpen(true)} className="p-2 rounded-lg hover:bg-gray-100">
            <Menu size={20} className="text-spa-dark" />
          </button>
          <p className="font-display font-bold text-spa-dark text-sm">Day Spa Admin</p>
          <div className="w-9" />
        </header>

        {/* Page content */}
        <main className="flex-1 p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
