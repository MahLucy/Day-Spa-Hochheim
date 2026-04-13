import { useState, useEffect } from 'react'
import { BrowserRouter, Routes, Route, useLocation } from 'react-router-dom'
import { CartProvider } from './hooks/useCart'

// Public pages
import Layout from './components/layout/Layout'
import HomePage from './pages/HomePage'
import ServicesPage from './pages/ServicesPage'
import CartPage from './pages/CartPage'
import PersonalDataPage from './pages/PersonalDataPage'
import PaymentPage from './pages/PaymentPage'
import SuccessPage from './pages/SuccessPage'
import ContactPage from './pages/ContactPage'

// Admin pages
import AdminLogin from './pages/admin/AdminLogin'
import AdminLayout from './pages/admin/AdminLayout'
import AdminDashboard from './pages/admin/AdminDashboard'
import AdminServices from './pages/admin/AdminServices'
import AdminOrders from './pages/admin/AdminOrders'
import AdminGiftCards from './pages/admin/AdminGiftCards'
import AdminInvoices from './pages/admin/AdminInvoices'
import AdminReports from './pages/admin/AdminReports'

// Scroll to top on route change
function ScrollToTopPage({ children }) {
  const { pathname } = useLocation()
  useEffect(() => { window.scrollTo({ top: 0 }) }, [pathname])
  return children
}

// Admin wrapper — handles login state
function AdminApp() {
  const [authenticated, setAuthenticated] = useState(
    () => !!sessionStorage.getItem('adminCredentials')
  )

  if (!authenticated) {
    return <AdminLogin onLogin={() => setAuthenticated(true)} />
  }

  return (
    <AdminLayout onLogout={() => setAuthenticated(false)}>
      {/* Outlet renders the matched child route */}
    </AdminLayout>
  )
}

export default function App() {
  return (
    <CartProvider>
      <BrowserRouter>
        <Routes>
          {/* ── Public routes (with Navbar + Footer) ─────────────── */}
          <Route element={<Layout />}>
            <Route path="/" element={<ScrollToTopPage><HomePage /></ScrollToTopPage>} />
            <Route path="/servicos" element={<ScrollToTopPage><ServicesPage /></ScrollToTopPage>} />
            <Route path="/carrinho" element={<ScrollToTopPage><CartPage /></ScrollToTopPage>} />
            <Route path="/dados-pessoais" element={<ScrollToTopPage><PersonalDataPage /></ScrollToTopPage>} />
            <Route path="/pagamento" element={<ScrollToTopPage><PaymentPage /></ScrollToTopPage>} />
            <Route path="/confirmacao" element={<ScrollToTopPage><SuccessPage /></ScrollToTopPage>} />
            <Route path="/contato" element={<ScrollToTopPage><ContactPage /></ScrollToTopPage>} />
          </Route>

          {/* ── Admin routes (standalone layout, no Navbar/Footer) ── */}
          <Route path="/admin" element={<AdminApp />}>
            <Route index element={<AdminDashboard />} />
            <Route path="servicos" element={<AdminServices />} />
            <Route path="pedidos" element={<AdminOrders />} />
            <Route path="cartoes" element={<AdminGiftCards />} />
            <Route path="notas" element={<AdminInvoices />} />
            <Route path="relatorios" element={<AdminReports />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </CartProvider>
  )
}
