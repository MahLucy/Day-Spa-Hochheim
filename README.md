# Day Spa Hochheim — Frontend Application

A fully responsive booking/e-commerce platform for wellness spa services, built with React + Vite + Tailwind CSS.

## 🚀 Quick Start

### Prerequisites
- Node.js 18+ installed

### Installation & Run

```bash
# 1. Enter the project folder
cd spa-app

# 2. Install dependencies
npm install

# 3. Start development server
npm run dev
```

Open [http://localhost:5173](http://localhost:5173) in your browser.

### Build for production

```bash
npm run build
npm run preview
```

---

## 📁 Project Structure

```
spa-app/
├── index.html
├── vite.config.js
├── tailwind.config.js
├── postcss.config.js
└── src/
    ├── main.jsx            # Entry point
    ├── App.jsx             # Router + providers
    ├── index.css           # Global styles + Tailwind
    ├── data/
    │   └── services.js     # All services & testimonials data
    ├── hooks/
    │   └── useCart.jsx     # Cart context/state
    ├── components/
    │   ├── layout/
    │   │   ├── Layout.jsx  # Page wrapper
    │   │   ├── Navbar.jsx  # Responsive navbar
    │   │   └── Footer.jsx  # Footer
    │   └── ui/
    │       ├── ServiceCard.jsx    # Reusable service card
    │       ├── OrderSummary.jsx   # Cart summary (checkout)
    │       └── CheckoutStepper.jsx # Progress indicator
    └── pages/
        ├── HomePage.jsx        # Landing page
        ├── ServicesPage.jsx    # Services catalog
        ├── CartPage.jsx        # Shopping cart
        ├── PersonalDataPage.jsx # Checkout step 1
        ├── PaymentPage.jsx     # Checkout step 2
        ├── SuccessPage.jsx     # Order confirmation
        └── ContactPage.jsx     # Contact page
```

---

## 📱 Pages

| Route | Page |
|-------|------|
| `/` | Home — hero, stats, services, testimonials, contact |
| `/servicos` | Services catalog with filters |
| `/carrinho` | Shopping cart + gift card selection |
| `/dados-pessoais` | Personal data form |
| `/pagamento` | Payment (Pix / Credit / Debit / Boleto) |
| `/confirmacao` | Success / booking confirmed |
| `/contato` | Contact page |

---

## 🎨 Design System

**Fonts:** Cormorant Garamond (headings) + DM Sans (body)

**Colors:**
- `spa-dark`: `#1a3a4a` — primary dark
- `spa-mid`: `#2d6a7f` — hover states
- `spa-light`: `#4a9bb5` — icons/accents
- `spa-accent`: `#7ecdc4` — highlights/CTAs
- `spa-pale`: `#e8f4f6` — backgrounds
- `spa-cream`: `#faf8f5` — page background

---

## ✅ Features

- ✅ Mobile-first, fully responsive (320px → 1440px+)
- ✅ Hamburger nav on mobile
- ✅ Cart with quantity management and global state
- ✅ Gift card selection
- ✅ Multi-step checkout with progress stepper
- ✅ Form validation with error messages
- ✅ Multiple payment methods (Pix, Credit, Debit, Boleto)
- ✅ Payment loading state simulation
- ✅ Order confirmation with reservation code + copy
- ✅ Service filtering by category and price range
- ✅ Testimonials carousel (mobile) / grid (desktop)
- ✅ Sticky order summary on checkout pages
- ✅ Smooth scroll-to-top on navigation
