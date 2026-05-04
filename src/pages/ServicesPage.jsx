import { useState, useEffect } from 'react'
import { SlidersHorizontal, Info, Search } from 'lucide-react'
import ServiceCard from '../components/ui/ServiceCard'
import { api } from '../services/api'

const PRICE_MAX = 1100

export default function ServicesPage() {
  const [searchQuery, setSearchQuery] = useState('')
  const [activeCategory, setActiveCategory] = useState('Todos')
  const [priceRange, setPriceRange] = useState([0, PRICE_MAX])
  const [showFilters, setShowFilters] = useState(false)
  const [services, setServices] = useState([])
  const [categories, setCategories] = useState(['Todos'])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [currentPage, setCurrentPage] = useState(1)

  const ITEMS_PER_PAGE = 9

  useEffect(() => {
    setCurrentPage(1)
  }, [searchQuery, activeCategory, priceRange])

  useEffect(() => {
    async function fetchServices() {
      try {
        const response = await api.getServices()
        if (response.data && Array.isArray(response.data)) {
          const normalized = response.data.map(s => ({
            id: Number(s.id),
            name: s.name,
            category: s.category,
            description: s.description,
            price: Number(s.price),
            duration: `${s.duration_minutes} min`,
            sessions: `${s.sessions} ${s.sessions > 1 ? 'sessões' : 'sessão'}`,
            image: s.image_url || 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=400&q=80',
          }))
          setServices(normalized)
          const uniqueCategories = ['Todos', ...new Set(normalized.map(s => s.category))]
          setCategories(uniqueCategories)
        }
      } catch (err) {
        setError('Não foi possível carregar os serviços. Tente novamente.')
      } finally {
        setLoading(false)
      }
    }
    fetchServices()
  }, [])

  const filtered = services.filter(s => {
    const searchMatch = searchQuery === '' || 
      s.name.toLowerCase().includes(searchQuery.toLowerCase()) || 
      (s.description && s.description.toLowerCase().includes(searchQuery.toLowerCase()))
    const catMatch = activeCategory === 'Todos' || s.category === activeCategory
    const priceMatch = s.price >= priceRange[0] && s.price <= priceRange[1]
    return searchMatch && catMatch && priceMatch
  })

  const totalPages = Math.ceil(filtered.length / ITEMS_PER_PAGE)
  const paginatedServices = filtered.slice(
    (currentPage - 1) * ITEMS_PER_PAGE,
    currentPage * ITEMS_PER_PAGE
  )

  const handlePageChange = (newPage) => {
    setCurrentPage(newPage)
    const el = document.getElementById('catalogo')
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  }

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
                searchQuery={searchQuery}
                setSearchQuery={setSearchQuery}
                activeCategory={activeCategory}
                setActiveCategory={setActiveCategory}
                priceRange={priceRange}
                setPriceRange={setPriceRange}
                categories={categories}
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
                    searchQuery={searchQuery}
                    setSearchQuery={setSearchQuery}
                    activeCategory={activeCategory}
                    setActiveCategory={setActiveCategory}
                    priceRange={priceRange}
                    setPriceRange={setPriceRange}
                    categories={categories}
                  />
                </div>
              )}
            </div>

            {/* Services grid */}
            <div className="flex-1">
              {loading ? (
                <div className="text-center py-16">
                  <p className="text-spa-muted font-body">Carregando serviços...</p>
                </div>
              ) : error ? (
                <div className="text-center py-16">
                  <p className="text-red-500 font-body">{error}</p>
                </div>
              ) : (
                <>
                  <div className="flex items-center justify-between mb-5">
                    <p className="text-sm font-body text-spa-muted">
                      Exibindo <span className="text-spa-dark font-medium">{filtered.length}</span> serviços
                    </p>
                  </div>

                  {/* Aviso compra individual */}
                  <div className="flex items-start gap-3 mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                    <Info size={18} className="text-amber-600 shrink-0 mt-0.5" />
                    <p className="text-sm font-body text-amber-800 leading-relaxed">
                      <strong>Atenção:</strong> A compra do cartão presente é individual — somente para 1 pessoa. Não é possível dividir os serviços entre pessoas diferentes (exceto pacotes especiais para casais).
                    </p>
                  </div>

                  {filtered.length === 0 ? (
                    <div className="text-center py-16">
                      <p className="text-spa-muted font-body">Nenhum serviço encontrado.</p>
                    </div>
                  ) : (
                    <>
                      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                        {paginatedServices.map(s => (
                          <ServiceCard key={s.id} service={s} />
                        ))}
                      </div>

                      {/* Paginação */}
                      {totalPages > 1 && (
                        <div className="flex flex-wrap items-center justify-center gap-2 mt-12">
                          <button
                            onClick={() => handlePageChange(Math.max(currentPage - 1, 1))}
                            disabled={currentPage === 1}
                            className="px-4 py-2 text-sm font-body font-medium text-spa-dark bg-spa-pale rounded-xl hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                          >
                            Anterior
                          </button>
                          
                          <div className="flex flex-wrap gap-1.5">
                            {Array.from({ length: totalPages }, (_, i) => i + 1).map(page => (
                              <button
                                key={page}
                                onClick={() => handlePageChange(page)}
                                className={`w-9 h-9 flex items-center justify-center text-sm font-body font-medium rounded-xl transition-colors ${
                                  currentPage === page
                                    ? 'bg-spa-dark text-white'
                                    : 'bg-spa-pale text-spa-dark hover:bg-gray-200'
                                }`}
                              >
                                {page}
                              </button>
                            ))}
                          </div>

                          <button
                            onClick={() => handlePageChange(Math.min(currentPage + 1, totalPages))}
                            disabled={currentPage === totalPages}
                            className="px-4 py-2 text-sm font-body font-medium text-spa-dark bg-spa-pale rounded-xl hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                          >
                            Próxima
                          </button>
                        </div>
                      )}
                    </>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
      </section>
    </div>
  )
}

function FilterPanel({ searchQuery, setSearchQuery, activeCategory, setActiveCategory, priceRange, setPriceRange, categories }) {
  const PRICE_MAX = 1100

  return (
    <div className="space-y-6">
      <div>
        <p className="text-sm font-body font-semibold text-spa-muted uppercase tracking-widest mb-4">Buscar</p>
        <div className="relative">
          <input
            type="text"
            placeholder="Pesquisar..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-10 pr-4 py-2.5 text-sm font-body bg-spa-pale border border-spa-dark/10 rounded-xl focus:border-spa-dark focus:ring-1 focus:ring-spa-dark transition-all outline-none"
          />
          <Search size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-spa-muted" />
        </div>
      </div>

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
