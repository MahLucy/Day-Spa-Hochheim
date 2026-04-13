import { Plus, Minus } from 'lucide-react'
import { useCart } from '../../hooks/useCart'

export default function ServiceCard({ service, compact = false }) {
  const { items, addItem, removeItem, updateQty } = useCart()

  const cartItem = items.find(i => i.id === service.id)

  const handleDecrease = () => {
    if (!cartItem) return
    if (cartItem.qty === 1) {
      removeItem(service.id)
    } else {
      updateQty(service.id, cartItem.qty - 1)
    }
  }

  return (
    <div className="bg-white rounded-2xl overflow-hidden shadow-card hover:shadow-hover transition-shadow duration-300 group flex flex-col h-full">
      {/* Image area */}
      <div className="relative overflow-hidden" style={{ height: compact ? '260px' : '220px' }}>
        <img
          src={service.image}
          alt={service.name}
          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
        />
        {/* Category tag overlapping bottom-left */}
        <span className="absolute bottom-3 left-3 bg-white/90 backdrop-blur-sm text-spa-dark text-[10px] font-body font-semibold px-3 py-1 rounded-sm uppercase tracking-[0.15em]">
          {service.category}
        </span>
      </div>

      {/* Content area */}
      <div className="p-4 flex flex-col flex-1">
        <h3 className="font-display text-base font-semibold text-spa-dark mb-1 leading-tight">
          {service.name}
        </h3>
        {!compact && (
          <p className="text-sm text-spa-muted font-body font-normal leading-relaxed mb-3 line-clamp-3">
            {service.description}
          </p>
        )}

        {/* Divider with duration */}
        <div className="mt-auto">
          <div className="border-t border-gray-200 pt-3 mb-3">
            <span className="text-sm text-spa-muted font-body font-medium">
              {service.duration}
            </span>
          </div>

          {/* Price row */}
          <div className="flex items-center justify-between">
            <div className="flex items-baseline">
              <span className="text-xl font-display font-semibold text-spa-dark">R$ {service.price}</span>
              <span className="text-sm text-spa-muted font-body font-medium ml-1">/sessão</span>
            </div>
            
            {cartItem ? (
              <div className="flex items-center gap-3 bg-spa-pale rounded-lg px-2 py-1.5 border border-spa-mid/20">
                <button
                  onClick={(e) => { e.preventDefault(); e.stopPropagation(); handleDecrease(); }}
                  className="w-6 h-6 flex items-center justify-center text-spa-mid hover:bg-white rounded transition-colors"
                >
                  <Minus size={14} strokeWidth={2.5} />
                </button>
                <span className="text-sm font-display font-semibold text-spa-dark w-4 text-center">
                  {cartItem.qty}
                </span>
                <button
                  onClick={(e) => { e.preventDefault(); e.stopPropagation(); addItem(service); }}
                  className="w-6 h-6 flex items-center justify-center text-spa-mid hover:bg-white rounded transition-colors"
                >
                  <Plus size={14} strokeWidth={2.5} />
                </button>
              </div>
            ) : (
              <button
                onClick={(e) => { e.preventDefault(); e.stopPropagation(); addItem(service); }}
                className="flex items-center gap-1.5 bg-spa-mid text-white text-xs font-body font-medium px-4 py-2.5 rounded-lg hover:bg-spa-dark transition-all duration-200 active:scale-95 shadow-sm"
              >
                <Plus size={14} strokeWidth={2.5} />
                Adicionar
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
