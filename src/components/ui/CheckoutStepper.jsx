import { Check } from 'lucide-react'

const steps = [
  { label: 'Carrinho' },
  { label: 'Dados Pessoais' },
  { label: 'Pagamento' },
  { label: 'Confirmação' },
]

export default function CheckoutStepper({ currentStep }) {
  // currentStep: 1=cart, 2=personal, 3=payment, 4=success
  return (
    <div className="flex items-center justify-center mb-8 lg:mb-12">
      {steps.map((step, i) => {
        const stepNum = i + 1
        const done = stepNum < currentStep
        const active = stepNum === currentStep

        return (
          <div key={step.label} className="flex items-center">
            <div className="flex flex-col items-center">
              <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-body font-medium transition-all ${
                done
                  ? 'bg-spa-accent text-spa-dark'
                  : active
                  ? 'bg-spa-dark text-white'
                  : 'bg-gray-200 text-spa-muted'
              }`}>
                {done ? <Check size={14} /> : stepNum}
              </div>
              <span className={`text-xs font-body mt-1 hidden sm:block transition-all ${
                active ? 'text-spa-dark font-medium' : 'text-spa-muted'
              }`}>
                {step.label}
              </span>
            </div>
            {i < steps.length - 1 && (
              <div className={`w-12 sm:w-20 h-0.5 mx-1 mb-4 sm:mb-0 transition-all ${
                stepNum < currentStep ? 'bg-spa-accent' : 'bg-gray-200'
              }`} />
            )}
          </div>
        )
      })}
    </div>
  )
}
