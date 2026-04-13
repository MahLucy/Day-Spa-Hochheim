import { Link, useNavigate } from 'react-router-dom'
import { Minus, Plus, Trash2, ChevronRight, ShoppingBag, Gift } from 'lucide-react'
import { useCart } from '../hooks/useCart'
import CheckoutStepper from '../components/ui/CheckoutStepper'
import OrderSummary from '../components/ui/OrderSummary'

export default function CartPage() {
  const { items, removeItem, updateQty, giftCard, setGiftCard, total } = useCart()
  const navigate = useNavigate()

  if (items.length === 0) {
    return (
      <div className="min-h-[80vh] pt-28 pb-12 lg:pt-36 lg:pb-20 flex flex-col items-center justify-center px-4 text-center">
        <div className="w-24 h-24 bg-spa-pale rounded-full flex items-center justify-center mb-6">
          <ShoppingBag size={36} className="text-spa-mid" />
        </div>
        <h2 className="font-display text-3xl font-bold text-spa-dark mb-3">Seu carrinho está vazio</h2>
        <p className="text-base text-spa-muted font-medium font-body mb-8">Adicione vivências e serviços para iniciar seu agendamento.</p>
        <Link to="/servicos" className="btn-primary py-4 px-8 text-base">Ir para o Catálogo</Link>
      </div>
    )
  }

  return (
    <div className="min-h-screen pt-28 pb-12 lg:pt-36 lg:pb-20">
      <div className="page-container">
        <CheckoutStepper currentStep={1} />

        <h1 className="font-display text-4xl lg:text-5xl text-spa-dark font-bold mb-3">Seu carrinho</h1>
        <p className="text-base text-spa-muted font-body mb-10 font-medium">Revise seus serviços e escolha seu cartão presente</p>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Items */}
          <div className="lg:col-span-2 space-y-4">
            {items.map(item => (
              <CartItem key={item.id} item={item} onRemove={removeItem} onQty={updateQty} />
            ))}

            {/* Gift card selection */}
            <div className="bg-white rounded-xl3 p-6 shadow-card">
              <div className="flex items-center gap-3 mb-5">
                <Gift size={20} className="text-spa-mid" />
                <h3 className="font-display text-xl text-spa-dark font-bold">Escolha o seu cartão Presente</h3>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <GiftCardOption
                  id="virtual"
                  label="Cartão virtual"
                  img="https://images.unsplash.com/photo-1600334089648-b0d9d3028eb2?w=400&q=80"
                  selected={giftCard === 'virtual'}
                  onSelect={() => setGiftCard(giftCard === 'virtual' ? null : 'virtual')}
                />
                <GiftCardOption
                  id="printed"
                  label="Cartão impresso"
                  img="https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=400&q=80"
                  selected={giftCard === 'printed'}
                  onSelect={() => setGiftCard(giftCard === 'printed' ? null : 'printed')}
                />
              </div>
            </div>
          </div>

          {/* Summary — sticky on desktop */}
          <div className="lg:col-span-1">
            <div className="lg:sticky lg:top-32 space-y-4">
              <OrderSummary />
              <button
                onClick={() => navigate('/dados-pessoais')}
                className="btn-primary w-full flex items-center justify-center gap-2 py-4 text-base"
              >
                Continuar
                <ChevronRight size={18} />
              </button>
              <p className="text-sm text-spa-muted font-body font-medium text-center">
                Pagamento seguro via Mercado Pago
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

function CartItem({ item, onRemove, onQty }) {
  return (
    <div className="bg-white rounded-xl3 p-5 shadow-card flex gap-5">
      <div className="w-24 h-24 rounded-xl overflow-hidden shrink-0">
        <img src={item.image} alt={item.name} className="w-full h-full object-cover" />
      </div>
      <div className="flex-1 min-w-0">
        <div className="flex items-start justify-between gap-2 mb-2">
          <div>
            <p className="text-sm font-semibold text-spa-muted font-body tracking-wide mb-1">{item.category}</p>
            <h4 className="font-display text-lg sm:text-xl text-spa-dark font-bold leading-tight">{item.name}</h4>
          </div>
          <button
            onClick={() => onRemove(item.id)}
            className="text-gray-400 hover:text-red-500 transition-colors mt-0.5 p-1"
            aria-label="Remover"
          >
            <Trash2 size={18} strokeWidth={2.5}/>
          </button>
        </div>
        <p className="text-sm font-medium text-spa-muted font-body mb-4">{item.sessions || item.duration}</p>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-1 border border-spa-mid/20 bg-spa-pale rounded-lg py-0.5 px-1">
            <button
              onClick={() => onQty(item.id, item.qty - 1)}
              className="w-8 h-8 flex items-center justify-center hover:bg-white text-spa-mid transition-colors rounded-md"
            >
              <Minus size={14} strokeWidth={2.5}/>
            </button>
            <span className="w-8 text-center text-base font-body font-semibold text-spa-dark">{item.qty}</span>
            <button
              onClick={() => onQty(item.id, item.qty + 1)}
              className="w-8 h-8 flex items-center justify-center hover:bg-white text-spa-mid transition-colors rounded-md"
            >
              <Plus size={14} strokeWidth={2.5}/>
            </button>
          </div>
          <span className="font-display text-xl sm:text-2xl text-spa-dark font-bold">R${item.price * item.qty}</span>
        </div>
      </div>
    </div>
  )
}

function GiftCardOption({ id, label, img, selected, onSelect }) {
  return (
    <button
      onClick={onSelect}
      className={`relative rounded-xl overflow-hidden border-2 transition-all text-left ${
        selected ? 'border-spa-dark' : 'border-transparent'
      }`}
      style={{ height: '120px' }}
    >
      <img src={img} alt={label} className="w-full h-full object-cover" />
      <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
      <div className="absolute bottom-3 left-3 right-3 flex items-center justify-between">
        <div>
          <p className="text-white font-display text-sm italic">relaxe, respire e sinta</p>
          <p className="text-white/80 text-xs font-body mt-0.5">cartão presente</p>
        </div>
      </div>
      {/* Radio indicator */}
      <div className="absolute top-3 left-3">
        <div className={`w-5 h-5 rounded-full border-2 border-white flex items-center justify-center ${
          selected ? 'bg-white' : 'bg-transparent'
        }`}>
          {selected && <div className="w-2.5 h-2.5 rounded-full bg-spa-dark" />}
        </div>
      </div>
      <div className="absolute bottom-0 left-0 right-0 bg-white/95 py-1.5 px-3">
        <p className="text-xs font-body font-medium text-spa-dark">{label}</p>
      </div>
    </button>
  )
}
