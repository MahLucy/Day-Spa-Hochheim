import { useState } from 'react'
import { SlidersHorizontal } from 'lucide-react'
import ServiceCard from '../components/ui/ServiceCard'
import { services, categories } from '../data/services'

const PRICE_MAX = 250

export default function ServicesPage() {
  const [activeCategory, setActiveCategory] = useState('Todos')
  const [priceRange, setPriceRange] = useState([0, PRICE_MAX])
  const [showFilters, setShowFilters] = useState(false)

  const filtered = services.filter(s => {
    const catMatch = activeCategory === 'Todos' || s.category === activeCategory
    const priceMatch = s.price >= priceRange[0] && s.price <= priceRange[1]
    return catMatch && priceMatch
  })

  return (
    <div className="min-h-screen">
      {/* Hero */}
      <section className="relative overflow-hidden bg-[url('/service.webp')] bg-no-repeat bg-center bg-cover h-[100vh] flex items-center">
        <div className="w-full px-4 sm:px-8 lg:px-20 xl:px-80 relative py-10 md:py-12 lg:py-14">
          <div className="max-w-xl text-spa-dark text-left">
            <p className="text-xs font-body font-medium tracking-[0.2em] uppercase mb-4">
              Catálogo Hochheim
            </p>

            <h1 className="font-display text-3xl sm:text-4xl lg:text-6xl font-bold leading-tight mb-6">
              Nossas <span className="text-spa-light">vivências</span> e serviços
            </h1>

            <p className="text-sm md:text-xl font-body leading-relaxed mb-8 max-w-lg">
              Do relaxamento profundo à revitalização energética — escolha a experiência perfeita para o seu momento.
            </p>
          </div>
        </div>
      </section>

      {/* Filters + Grid */}
      <section id="catalogo" className="py-10 lg:py-14">
        <div className="page-container">
          <div className="flex flex-col lg:flex-row gap-8">
            {/* Filter sidebar — desktop */}
            <aside className="hidden lg:block w-52 shrink-0">
              <FilterPanel
                activeCategory={activeCategory}
                setActiveCategory={setActiveCategory}
                priceRange={priceRange}
                setPriceRange={setPriceRange}
              />
            </aside>

            {/* Mobile filter toggle */}
            <div className="lg:hidden">
              <button
                onClick={() => setShowFilters(!showFilters)}
                className="flex items-center gap-2 text-sm font-body font-medium text-spa-dark border border-gray-200 px-4 py-2.5 rounded-xl hover:bg-gray-50 transition-colors"
              >
                <SlidersHorizontal size={16} />
                Filtros
                {(activeCategory !== 'Todos' || priceRange[1] < PRICE_MAX) && (
                  <span className="w-2 h-2 rounded-full bg-spa-accent" />
                )}
              </button>
              {showFilters && (
                <div className="mt-4 p-4 bg-white rounded-xl3 shadow-card border border-gray-100">
                  <FilterPanel
                    activeCategory={activeCategory}
                    setActiveCategory={setActiveCategory}
                    priceRange={priceRange}
                    setPriceRange={setPriceRange}
                  />
                </div>
              )}
            </div>

            {/* Services grid */}
            <div className="flex-1">
              <div className="flex items-center justify-between mb-5">
                <p className="text-sm font-body text-spa-muted">
                  Exibindo <span className="text-spa-dark font-medium">{filtered.length}</span> serviços
                </p>
              </div>

              {filtered.length === 0 ? (
                <div className="text-center py-16">
                  <p className="text-spa-muted font-body">Nenhum serviço encontrado.</p>
                </div>
              ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                  {filtered.map(s => (
                    <ServiceCard key={s.id} service={s} />
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </section>
    </div>
  )
}

function FilterPanel({ activeCategory, setActiveCategory, priceRange, setPriceRange }) {
  const PRICE_MAX = 250

  return (
    <div className="space-y-6">
      <div>
        <p className="text-sm font-body font-semibold text-spa-muted uppercase tracking-widest mb-4">Categoria</p>
        <div className="flex flex-wrap lg:flex-col gap-2">
          {categories.map(c => (
            <button
              key={c}
              onClick={() => setActiveCategory(c)}
              className={`text-base font-body font-medium px-4 py-2.5 rounded-lg text-left transition-all ${
                activeCategory === c
                  ? 'bg-spa-dark text-white'
                  : 'text-spa-muted hover:text-spa-dark hover:bg-spa-pale'
              }`}
            >
              {c}
            </button>
          ))}
        </div>
      </div>

      <div>
        <p className="text-sm font-body font-semibold text-spa-muted uppercase tracking-widest mb-4">Preço</p>
        <div className="flex items-center gap-2 mb-3">
          <span className="text-sm font-body font-medium text-spa-muted">Até</span>
          <span className="text-base font-body font-bold text-spa-dark">R${priceRange[1]}</span>
        </div>
        <input
          type="range"
          min={0}
          max={PRICE_MAX}
          step={10}
          value={priceRange[1]}
          onChange={e => setPriceRange([0, Number(e.target.value)])}
          className="w-full accent-spa-dark"
        />
        <div className="flex justify-between text-sm font-body font-medium text-spa-muted mt-2">
          <span>R$0</span>
          <span>R${PRICE_MAX}</span>
        </div>
      </div>
    </div>
  )
}
