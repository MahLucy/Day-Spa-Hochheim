import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { ChevronLeft, ChevronRight, AlertCircle } from 'lucide-react'
import CheckoutStepper from '../components/ui/CheckoutStepper'
import OrderSummary from '../components/ui/OrderSummary'
import { useCart } from '../hooks/useCart'
import {
  validateCpf, formatCpf,
  validatePhone, formatPhone,
  validateEmail, validateDate, formatDate,
  sanitize,
} from '../services/validation'

const initialForm = {
  name: '', cpf: '', birthdate: '', gender: '',
  email: '', emailConfirm: '', phone: '', whatsapp: '',
  recipient_name: '', recipient_phone: '', recipient_email: '',
  consent1: false, consent2: false, consent3: false,
}

export default function PersonalDataPage() {
  const { items } = useCart()
  const navigate             = useNavigate()
  const [form, setForm]     = useState(initialForm)
  const [errors, setErrors] = useState({})
  const [touched, setTouched] = useState({})

  // Guarda: carrinho vazio → volta ao carrinho
  useEffect(() => {
    if (items.length === 0) navigate('/carrinho', { replace: true })
  }, [items, navigate])

  // Recupera dados preenchidos anteriormente na sessão
  useEffect(() => {
    try {
      const saved = sessionStorage.getItem('checkout_customer')
      if (saved) setForm(prev => ({ ...prev, ...JSON.parse(saved) }))
    } catch { /* ignora */ }
  }, [])

  const set = (key, val) => {
    setForm(f => ({ ...f, [key]: val }))
    if (touched[key]) validateField(key, val)
  }

  const touch = (key) => setTouched(t => ({ ...t, [key]: true }))

  // Validação de campo individual (feedback em tempo real)
  const validateField = (key, val = form[key]) => {
    const e = {}
    switch (key) {
      case 'name':
        if (!String(val).trim()) e.name = 'Nome obrigatório.'
        else if (String(val).trim().length < 3) e.name = 'Nome muito curto.'
        break
      case 'cpf':
        if (!String(val).trim()) e.cpf = 'CPF obrigatório.'
        else if (!validateCpf(val)) e.cpf = 'CPF inválido.'
        break
      case 'birthdate':
        if (val && !validateDate(val)) e.birthdate = 'Data inválida (dd/mm/aaaa).'
        break
      case 'email':
        if (!String(val).trim()) e.email = 'E-mail obrigatório.'
        else if (!validateEmail(val)) e.email = 'E-mail inválido.'
        break
      case 'emailConfirm':
        if (val !== form.email) e.emailConfirm = 'E-mails não coincidem.'
        break
      case 'phone':
        if (!String(val).trim()) e.phone = 'Telefone obrigatório.'
        else if (!validatePhone(val)) e.phone = 'Telefone inválido.'
        break
      case 'gender':
        if (!val) e.gender = 'Selecione o gênero.'
        break
      case 'recipient_name':
        if (!String(val).trim()) e.recipient_name = 'Nome do presenteado obrigatório.'
        else if (String(val).trim().length < 3) e.recipient_name = 'Nome muito curto.'
        break
      case 'recipient_email':
        if (!String(val).trim()) e.recipient_email = 'E-mail do presenteado obrigatório.'
        else if (!validateEmail(val)) e.recipient_email = 'E-mail inválido.'
        break
      case 'recipient_phone':
        if (!String(val).trim()) e.recipient_phone = 'Telefone do presenteado obrigatório.'
        else if (!validatePhone(val)) e.recipient_phone = 'Telefone inválido.'
        break
      default: break
    }
    setErrors(prev => {
      const next = { ...prev }
      if (e[key]) next[key] = e[key]
      else delete next[key]
      return next
    })
    return !e[key]
  }

  const validateAll = () => {
    const e = {}
    const name = sanitize(form.name)
    if (!name || name.length < 3) e.name = 'Nome obrigatório (mín. 3 caracteres).'
    if (!validateCpf(form.cpf))   e.cpf  = 'CPF inválido.'
    if (form.birthdate && !validateDate(form.birthdate)) e.birthdate = 'Data inválida.'
    if (!validateEmail(form.email))              e.email        = 'E-mail inválido.'
    if (form.email !== form.emailConfirm)        e.emailConfirm = 'E-mails não coincidem.'
    if (!validatePhone(form.phone))              e.phone        = 'Telefone inválido.'
    if (!form.gender)                                          e.gender          = 'Selecione o gênero.'
    const rName = sanitize(form.recipient_name)
    if (!rName || rName.length < 3)                      e.recipient_name  = 'Nome do presenteado obrigatório (mín. 3 caracteres).'
    if (!validateEmail(form.recipient_email))             e.recipient_email = 'E-mail do presenteado obrigatório.'
    if (!validatePhone(form.recipient_phone))             e.recipient_phone = 'Telefone do presenteado obrigatório.'
    if (!form.consent1)                                        e.consent1        = 'Aceite obrigatório.'
    return e
  }

  const handleSubmit = (e) => {
    e.preventDefault()

    // Marca todos os campos como tocados
    setTouched({ name: true, cpf: true, birthdate: true, email: true, emailConfirm: true, phone: true, gender: true, recipient_name: true, recipient_email: true, recipient_phone: true })

    const errs = validateAll()
    if (Object.keys(errs).length) {
      setErrors(errs)
      // Rola para o primeiro erro
      const firstField = document.querySelector('[data-field-error]')
      firstField?.scrollIntoView({ behavior: 'smooth', block: 'center' })
      return
    }

    // Salva dados na sessão (limpos)
    const customerData = {
      name:      sanitize(form.name),
      cpf:       form.cpf.replace(/\D/g, ''),
      // MySQL DATE exige yyyy-mm-dd; o campo exibe dd/mm/yyyy
      birthdate: form.birthdate && form.birthdate.includes('/')
        ? form.birthdate.split('/').reverse().join('-')
        : (form.birthdate || null),
      gender:    form.gender === 'Feminino' ? 'F' : form.gender === 'Masculino' ? 'M' : 'O',
      email:     form.email.trim().toLowerCase(),
      phone:     form.phone.replace(/\D/g, ''),
      whatsapp:  form.whatsapp ? form.whatsapp.replace(/\D/g, '') : null,
      consent2:  form.consent2,
      consent3:  form.consent3,
      recipient_name:  sanitize(form.recipient_name),
      recipient_email: form.recipient_email.trim().toLowerCase(),
      recipient_phone: form.recipient_phone.replace(/\D/g, ''),
    }

    try {
      sessionStorage.setItem('checkout_customer', JSON.stringify(customerData))
    } catch { /* sessionStorage indisponível — continua mesmo assim */ }

    navigate('/pagamento')
  }

  return (
    <div className="min-h-screen pt-28 pb-12 lg:pt-36 lg:pb-20">
      <div className="page-container">
        <CheckoutStepper currentStep={2} />

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Form */}
          <div className="lg:col-span-2">
            <h1 className="font-display text-4xl lg:text-5xl text-spa-dark font-bold mb-3">
              Seus dados <span className="font-semibold">pessoais</span>
            </h1>
            <p className="text-base font-medium text-spa-muted font-body mb-10">
              Suas informações são protegidas e usadas apenas para confirmar o agendamento e enviar o cartão.
            </p>

            <form onSubmit={handleSubmit} className="space-y-8" noValidate>
              {/* Seção 1 — Identificação */}
              <FormSection number="1" title="Identificação">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="sm:col-span-2">
                    <FormField label="Nome completo *" error={errors.name}>
                      <input
                        className={`input-field ${errors.name ? 'border-red-400 focus:ring-red-400' : ''}`}
                        placeholder="Seu nome completo"
                        value={form.name}
                        onChange={e => set('name', e.target.value)}
                        onBlur={() => touch('name')}
                        autoComplete="name"
                        data-field-error={errors.name || undefined}
                      />
                    </FormField>
                  </div>

                  <FormField label="CPF *" error={errors.cpf}>
                    <input
                      className={`input-field font-mono tracking-wider ${errors.cpf ? 'border-red-400' : ''}`}
                      placeholder="000.000.000-00"
                      value={form.cpf}
                      onChange={e => set('cpf', formatCpf(e.target.value))}
                      onBlur={() => touch('cpf')}
                      inputMode="numeric"
                      maxLength={14}
                      autoComplete="off"
                      data-field-error={errors.cpf || undefined}
                    />
                  </FormField>

                  <FormField label="Data de nascimento" error={errors.birthdate}>
                    <input
                      className="input-field"
                      placeholder="dd/mm/aaaa"
                      value={form.birthdate}
                      onChange={e => set('birthdate', formatDate(e.target.value))}
                      onBlur={() => touch('birthdate')}
                      inputMode="numeric"
                      maxLength={10}
                      autoComplete="bday"
                    />
                  </FormField>
                </div>

                {/* Gênero */}
                <div>
                  <p className="text-sm font-body font-semibold text-spa-muted uppercase tracking-widest mb-3">
                    Gênero *
                  </p>
                  {errors.gender && (
                    <p className="flex items-center gap-1 text-red-500 text-xs font-body mb-2">
                      <AlertCircle size={12} /> {errors.gender}
                    </p>
                  )}
                  <div className="flex flex-wrap gap-3">
                    {['Feminino', 'Masculino', 'Não Binário', 'Prefiro não informar'].map(g => (
                      <label key={g} className={`flex items-center gap-2 px-4 py-2.5 rounded-xl border cursor-pointer transition-all text-sm font-body ${
                        form.gender === g
                          ? 'border-spa-dark bg-spa-pale text-spa-dark font-medium'
                          : 'border-gray-200 text-spa-muted hover:border-gray-300'
                      }`}>
                        <input type="radio" name="gender" className="sr-only"
                          checked={form.gender === g}
                          onChange={() => set('gender', g)} />
                        {g}
                      </label>
                    ))}
                  </div>
                </div>
              </FormSection>

              {/* Seção 2 — Contato */}
              <FormSection number="2" title="Contato">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <FormField label="E-mail *" error={errors.email}>
                    <input
                      type="email"
                      className={`input-field ${errors.email ? 'border-red-400' : ''}`}
                      placeholder="seu@email.com"
                      value={form.email}
                      onChange={e => set('email', e.target.value)}
                      onBlur={() => touch('email')}
                      autoComplete="email"
                    />
                  </FormField>

                  <FormField label="Confirmar e-mail *" error={errors.emailConfirm}>
                    <input
                      type="email"
                      className={`input-field ${errors.emailConfirm ? 'border-red-400' : ''}`}
                      placeholder="Repita seu e-mail"
                      value={form.emailConfirm}
                      onChange={e => set('emailConfirm', e.target.value)}
                      onBlur={() => touch('emailConfirm')}
                      autoComplete="email"
                      onPaste={e => e.preventDefault()} // evita colar o mesmo e-mail
                    />
                  </FormField>

                  <FormField label="Telefone *" error={errors.phone}>
                    <input
                      className={`input-field ${errors.phone ? 'border-red-400' : ''}`}
                      placeholder="(47) 9 0000-0000"
                      value={form.phone}
                      onChange={e => set('phone', formatPhone(e.target.value))}
                      onBlur={() => touch('phone')}
                      inputMode="numeric"
                      autoComplete="tel"
                    />
                  </FormField>

                  <FormField label="WhatsApp" error={null}>
                    <input
                      className="input-field"
                      placeholder="(47) 9 0000-0000"
                      value={form.whatsapp}
                      onChange={e => set('whatsapp', formatPhone(e.target.value))}
                      inputMode="numeric"
                      autoComplete="tel"
                    />
                    <p className="text-xs text-spa-muted font-body mt-1">
                      Opcional — se diferente do telefone
                    </p>
                  </FormField>
                </div>
              </FormSection>

              {/* Seção 3 — Presenteado */}
              <FormSection number="3" title="Para quem será o cartão?">
                <p className="text-sm font-body text-spa-muted -mt-1">
                  Informe os dados de quem vai receber o presente. O cartão será enviado por e-mail a essa pessoa também.
                </p>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="sm:col-span-2">
                    <FormField label="Nome do presenteado *" error={errors.recipient_name}>
                      <input
                        className={`input-field ${errors.recipient_name ? 'border-red-400 focus:ring-red-400' : ''}`}
                        placeholder="Nome completo de quem vai receber"
                        value={form.recipient_name}
                        onChange={e => set('recipient_name', e.target.value)}
                        onBlur={() => touch('recipient_name')}
                        autoComplete="off"
                        data-field-error={errors.recipient_name || undefined}
                      />
                    </FormField>
                  </div>

                  <FormField label="Telefone do presenteado *" error={errors.recipient_phone}>
                    <input
                      className={`input-field ${errors.recipient_phone ? 'border-red-400' : ''}`}
                      placeholder="(47) 9 0000-0000"
                      value={form.recipient_phone}
                      onChange={e => set('recipient_phone', formatPhone(e.target.value))}
                      onBlur={() => touch('recipient_phone')}
                      inputMode="numeric"
                      autoComplete="off"
                      data-field-error={errors.recipient_phone || undefined}
                    />
                  </FormField>

                  <FormField label="E-mail do presenteado *" error={errors.recipient_email}>
                    <input
                      type="email"
                      className={`input-field ${errors.recipient_email ? 'border-red-400' : ''}`}
                      placeholder="email@dopresenteado.com"
                      value={form.recipient_email}
                      onChange={e => set('recipient_email', e.target.value)}
                      onBlur={() => touch('recipient_email')}
                      autoComplete="off"
                      data-field-error={errors.recipient_email || undefined}
                    />
                  </FormField>
                </div>
              </FormSection>

              {/* Seção 4 — Consentimento */}
              <FormSection number="4" title="Consentimento">
                <div className="space-y-3">
                  {[
                    {
                      key: 'consent1',
                      required: true,
                      text: 'Confirmo que as informações prestadas são verdadeiras e li a política de uso e consentimento.',
                    },
                    {
                      key: 'consent2',
                      required: false,
                      text: 'Autorizo o Day Spa Hochheim a entrar em contato via WhatsApp e e-mail para confirmação e lembretes do agendamento.',
                    },
                    {
                      key: 'consent3',
                      required: false,
                      text: 'Confirmo que li a política de gestão de saúde e privacidade.',
                    },
                  ].map(c => (
                    <label key={c.key} className="flex items-start gap-3 cursor-pointer group">
                      <div className={`w-5 h-5 rounded border-2 flex items-center justify-center shrink-0 mt-0.5 transition-all ${
                        form[c.key]
                          ? 'bg-spa-dark border-spa-dark'
                          : c.key === 'consent1' && errors.consent1
                            ? 'border-red-400'
                            : 'border-gray-300 group-hover:border-spa-light'
                      }`}>
                        {form[c.key] && (
                          <svg className="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                          </svg>
                        )}
                      </div>
                      <input type="checkbox" className="sr-only" checked={form[c.key]}
                        onChange={e => set(c.key, e.target.checked)} />
                      <p className="text-xs font-body text-spa-muted leading-relaxed">
                        {c.required && <span className="text-red-400">*</span>} {c.text}
                      </p>
                    </label>
                  ))}
                  {errors.consent1 && (
                    <p className="flex items-center gap-1 text-red-500 text-xs font-body">
                      <AlertCircle size={12} /> {errors.consent1}
                    </p>
                  )}
                </div>
              </FormSection>

              {/* Navegação */}
              <div className="flex items-center justify-between pt-2">
                <button type="button" onClick={() => navigate('/carrinho')}
                  className="flex items-center gap-1.5 text-sm font-body text-spa-muted hover:text-spa-dark transition-colors px-4 py-3 rounded-xl hover:bg-gray-50">
                  <ChevronLeft size={16} />
                  Voltar
                </button>
                <button type="submit"
                  className="btn-primary flex items-center gap-2 py-3.5 px-8">
                  Ir para o pagamento
                  <ChevronRight size={16} />
                </button>
              </div>
            </form>
          </div>

          {/* Resumo */}
          <div className="lg:col-span-1">
            <div className="lg:sticky lg:top-24">
              <OrderSummary />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

function FormSection({ number, title, children }) {
  return (
    <div className="bg-white rounded-xl3 p-6 shadow-card space-y-5">
      <div className="flex items-center gap-3">
        <div className="w-7 h-7 rounded-full bg-spa-dark text-white text-sm font-body font-medium flex items-center justify-center shrink-0">
          {number}
        </div>
        <h3 className="font-display text-2xl text-spa-dark font-bold">{title}</h3>
      </div>
      {children}
    </div>
  )
}

function FormField({ label, error, children }) {
  return (
    <div data-field-error={error || undefined}>
      <label className="block text-sm font-body font-semibold text-spa-muted mb-2">{label}</label>
      {children}
      {error && (
        <p className="flex items-center gap-1 text-red-500 text-xs font-body mt-1">
          <AlertCircle size={11} /> {error}
        </p>
      )}
    </div>
  )
}
