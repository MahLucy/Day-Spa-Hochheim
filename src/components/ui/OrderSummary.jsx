import { useCart } from '../../hooks/useCart'

export default function OrderSummary({ className = '' }) {
  const { items, subtotal, total } = useCart()

  return (
    <div className={`bg-spa-pale rounded-xl3 p-5 ${className}`}>
      <h3 className="font-display text-lg text-spa-dark font-medium mb-4">Resumo do pedido</h3>
      <div className="space-y-2 mb-4">
        {items.map(item => (
          <div key={item.id} className="flex items-start justify-between gap-2">
            <div className="flex-1 min-w-0">
              <p className="text-sm font-body text-spa-text truncate">{item.name}</p>
              <p className="text-xs text-spa-muted font-body">{item.qty}x {item.sessions}</p>
            </div>
            <span className="text-sm font-body font-medium text-spa-dark shrink-0">
              R${item.price * item.qty}
            </span>
          </div>
        ))}
      </div>
      <div className="border-t border-spa-accent/30 pt-3 space-y-2">
        <div className="flex justify-between text-sm font-body">
          <span className="text-spa-muted">Subtotal</span>
          <span className="text-spa-dark">R${subtotal}</span>
        </div>
        <div className="flex justify-between text-base font-body font-semibold border-t border-spa-accent/30 pt-2">
          <span className="text-spa-dark">Total</span>
          <span className="text-spa-dark">R${total}</span>
        </div>
      </div>
    </div>
  )
}
