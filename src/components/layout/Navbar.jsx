import { useState, useEffect } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { ShoppingCart, Menu, X, Waves } from 'lucide-react'
import { useCart } from '../../hooks/useCart'

export default function Navbar() {
  const [open, setOpen] = useState(false)
  const { count } = useCart()
  const [scrolled, setScrolled] = useState(false)
  const location = useLocation()

  useEffect(() => {
    setOpen(false)
  }, [location])

  useEffect(() => {
  const handleScroll = () => {
    if (window.scrollY > 50) {
      setScrolled(true)
    } else {
      setScrolled(false)
    }
  }

  window.addEventListener('scroll', handleScroll)

  return () => window.removeEventListener('scroll', handleScroll)
}, [])

  const links = [
    { to: '/', label: 'Home' },
    { to: '/servicos', label: 'Serviços' },
    { to: '/contato', label: 'Contato' },
  ]

  const normalize = (s) => (s || '/').toLowerCase().replace(/\/+$/, '') || '/'
  const path = normalize(location.pathname)
  const isActive = (href) => {
    const h = normalize(href)
    if (h === '/') return path === '/'
    return path === h || path.startsWith(h + '/')
  }

  return (
    <header
  className={`fixed top-0 left-0 w-full z-50 transition-all duration-300 ${
    scrolled
      ? 'bg-white shadow-md py-3'
      : 'bg-transparent py-6'
  }`}
>
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between gap-6">
          {/* Logo */}
          <Link to="/" className="flex items-center gap-2 hover:opacity-90 transition-opacity">
            <img
              src="/logo.png"
              alt="Day Spa Hochheim"
              className="h-12 w-auto md:h-20"
              draggable={false}
            />
          </Link>

          {/* Desktop Nav */}
          <nav className="hidden md:flex gap-6 lg:gap-12 mx-auto">
            {links.map(l => {
              const activeNow = isActive(l.to)
              return (
                <Link
                  key={l.to}
                  to={l.to}
                  className={`text-lg xl:text-xl transition-all duration-200 ${
                    activeNow
                      ? 'font-bold text-spa-dark'
                      : 'font-extralight text-spa-mid hover:font-bold hover:text-spa-dark'
                  }`}
                >
                  {l.label}
                </Link>
              )
            })}
          </nav>

          {/* Carrinho + Mobile Menu */}
          <div className="flex items-center gap-4">
            <Link
              to="/carrinho"
              className="hidden md:inline-flex items-center gap-2 border-2 border-spa-accent bg-white text-spa-mid px-6 py-3 rounded-full text-xl font-thin hover:bg-spa-accent hover:text-white transition-all duration-200"
            >
              <ShoppingCart size={20} />
              Carrinho
              {count > 0 && (
                <span className="bg-spa-accent text-spa-dark text-sm w-6 h-6 rounded-full flex items-center justify-center font-bold ml-2">
                  {count}
                </span>
              )}
            </Link>
            <button
              onClick={() => setOpen(!open)}
              className="md:hidden bg-spa-mid text-white p-3 rounded-full shadow-lg hover:bg-spa-dark hover:scale-105 active:scale-95 transition-all duration-200"
              aria-label="Menu"
            >
              {open ? <X size={24} /> : <Menu size={24} />}
            </button>
          </div>
        </div>
      </div>

      {/* Mobile Menu */}
      {open && (
        <div className="md:hidden bg-white border-t border-gray-200 px-4 py-4">
          <div className="space-y-4">
            {links.map(l => {
              const activeNow = isActive(l.to)
              return (
                <Link
                  key={l.to}
                  to={l.to}
                  className={`block text-lg transition-all ${
                    activeNow
                      ? 'font-bold text-spa-dark'
                      : 'font-light text-spa-mid hover:font-bold hover:text-spa-dark'
                  }`}
                  onClick={() => setOpen(false)}
                >
                  {l.label}
                </Link>
              )
            })}
            <Link
              to="/carrinho"
              className="flex items-center gap-2 border-2 border-spa-accent bg-white text-spa-mid px-4 py-2 rounded-full text-base font-thin hover:bg-spa-accent hover:text-white transition-all"
              onClick={() => setOpen(false)}
            >
              <ShoppingCart size={18} />
              Carrinho
              {count > 0 && (
                <span className="bg-spa-accent text-spa-dark text-sm w-5 h-5 rounded-full flex items-center justify-center font-bold ml-2">
                  {count}
                </span>
              )}
            </Link>
          </div>
        </div>
      )}
    </header>
  )
}
