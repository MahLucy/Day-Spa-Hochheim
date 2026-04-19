import { createContext, useContext, useState, useCallback, useEffect } from 'react'

const CartContext = createContext(null)

const STORAGE_KEY = 'spa_cart_v1'

function loadFromStorage() {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return { items: [], giftCard: null }
    return JSON.parse(raw)
  } catch {
    return { items: [], giftCard: null }
  }
}

function saveToStorage(items, giftCard) {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ items, giftCard }))
  } catch {
    // sessionStorage indisponível
  }
}

export function CartProvider({ children }) {
  const [items, setItems]       = useState(() => loadFromStorage().items)
  const [giftCard, setGiftCardState] = useState(() => loadFromStorage().giftCard)

  // Persiste sempre que itens ou giftCard mudam
  useEffect(() => {
    saveToStorage(items, giftCard)
  }, [items, giftCard])

  const addItem = useCallback((service) => {
    setItems(prev => {
      const existing = prev.find(i => i.id === service.id)
      if (existing) {
        return prev.map(i => i.id === service.id ? { ...i, qty: i.qty + 1 } : i)
      }
      return [...prev, { ...service, qty: 1 }]
    })
  }, [])

  const removeItem = useCallback((id) => {
    setItems(prev => prev.filter(i => i.id !== id))
  }, [])

  const updateQty = useCallback((id, qty) => {
    if (qty < 1) return
    setItems(prev => prev.map(i => i.id === id ? { ...i, qty } : i))
  }, [])

  const setGiftCard = useCallback((value) => {
    setGiftCardState(value)
  }, [])

  const clearCart = useCallback(() => {
    setItems([])
    setGiftCardState(null)
    try {
      sessionStorage.removeItem(STORAGE_KEY)
      // Limpa também dados pessoais salvos durante o checkout
      sessionStorage.removeItem('checkout_customer')
      sessionStorage.removeItem('checkout_order_id')
    } catch { /* silencioso */ }
  }, [])

  const subtotal        = items.reduce((sum, i) => sum + i.price * i.qty, 0)
  const total           = subtotal

  return (
    <CartContext.Provider value={{
      items, addItem, removeItem, updateQty, clearCart,
      giftCard, setGiftCard,
      subtotal, total,
      count: items.reduce((s, i) => s + i.qty, 0),
    }}>
      {children}
    </CartContext.Provider>
  )
}

export function useCart() {
  const ctx = useContext(CartContext)
  if (!ctx) throw new Error('useCart must be inside CartProvider')
  return ctx
}
