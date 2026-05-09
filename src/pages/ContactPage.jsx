import { useState } from 'react'
import { MapPin, Phone, Clock, Mail, Send, Instagram, MessageCircle, AlertCircle, CheckCircle } from 'lucide-react'
import { validateEmail, validatePhone, formatPhone, sanitize } from '../services/validation'
import { api, ApiError } from '../services/api'

const infos = [
  {
    icon: MapPin,
    title: 'Endereço',
    lines: ['Rua Itaiópolis, 102 – CEP 89012-084', 'Blumenau – SC'],
  },
  {
    icon: Phone,
    title: 'Telefone',
    lines: ['(47) 3037-1707'],
  },
  {
    icon: Mail,
    title: 'E-mail',
    lines: ['contato@spahochheim.com.br'],
  },
  {
    icon: Clock,
    title: 'Horários',
    lines: ['Seg – Sex: 13:00 às 21:00', 'Sábado: Fechado', 'Domingo: Fechado'],
  },
]

const initialForm = { name: '', phone: '', email: '', subject: '', message: '' }

export default function ContactPage() {
  const [form, setForm]       = useState(initialForm)
  const [errors, setErrors]   = useState({})
  const [sent, setSent]       = useState(false)
  const [loading, setLoading] = useState(false)
  const [apiError, setApiError] = useState('')

  const set = (k, v) => {
    setForm(f => ({ ...f, [k]: v }))
    if (errors[k]) setErrors(e => { const n = { ...e }; delete n[k]; return n })
  }

  const validate = () => {
    const e = {}
    if (!sanitize(form.name) || sanitize(form.name).length < 2) e.name = 'Nome obrigatório.'
    if (!validateEmail(form.email))   e.email   = 'E-mail inválido.'
    if (form.phone && !validatePhone(form.phone)) e.phone = 'Telefone inválido.'
    if (!sanitize(form.message) || sanitize(form.message).length < 10) e.message = 'Mensagem muito curta (mín. 10 caracteres).'
    return e
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setApiError('')

    const errs = validate()
    if (Object.keys(errs).length) { setErrors(errs); return }

    setLoading(true)
    try {
      await api.sendContact({
        name:    sanitize(form.name),
        email:   form.email.trim().toLowerCase(),
        phone:   form.phone ? form.phone.replace(/\D/g, '') : null,
        subject: sanitize(form.subject),
        message: sanitize(form.message),
      })
      setSent(true)
    } catch (err) {
      if (err instanceof ApiError && err.errors && Object.keys(err.errors).length) {
        setErrors(err.errors)
      } else {
        setApiError(err instanceof ApiError ? err.message : 'Falha ao enviar. Tente novamente.')
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen">
      {/* Hero */}
      <section className="bg-spa-dark pt-28 pb-16 lg:pt-36 lg:pb-20 rounded-b-[3rem] relative overflow-hidden">
        <div className="absolute inset-0 opacity-10 bg-[url('/header.webp')] bg-cover bg-center" />
        <div className="page-container flex flex-col items-center text-center relative z-10">
          <h1 className="font-display text-4xl sm:text-5xl lg:text-6xl text-white font-bold leading-tight mb-6 max-w-3xl">
            Entre em Contato
          </h1>
          <div className="flex items-center gap-2 mb-6 opacity-90">
            <div className="w-12 md:w-16 h-[3px] bg-spa-accent rounded-full" />
            <div className="w-2 h-2 bg-spa-accent rotate-45 rounded-[2px]" />
            <div className="w-12 md:w-16 h-[3px] bg-spa-accent rounded-full" />
          </div>
          <p className="text-base sm:text-lg lg:text-xl font-body text-white/80 max-w-2xl font-extralight leading-relaxed">
            Nossa equipe está à disposição para tirar suas dúvidas, agendar seus horários e ajudar a encontrar a experiência perfeita.
          </p>
        </div>
      </section>

      {/* Conteúdo */}
      <section className="py-12 lg:py-16">
        <div className="page-container">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
            {/* Informações */}
            <div>
              <h2 className="section-title mb-8">Informações de contato</h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-8">
                {infos.map(info => {
                  const Icon = info.icon
                  return (
                    <div key={info.title} className="bg-white rounded-xl3 p-5 shadow-card">
                      <div className="w-10 h-10 bg-spa-pale rounded-full flex items-center justify-center mb-3">
                        <Icon size={18} className="text-spa-light" />
                      </div>
                      <p className="text-xs font-body font-medium text-spa-muted uppercase tracking-wider mb-2">{info.title}</p>
                      {info.lines.map((line, i) => (
                        <p key={i} className={`text-sm font-body break-all ${i === 0 ? 'text-spa-dark font-medium' : 'text-spa-muted'}`}>
                          {line}
                        </p>
                      ))}
                    </div>
                  )
                })}
              </div>
              <div className="flex flex-col sm:flex-row gap-3">
                <a href="https://wa.me/5547991151707" target="_blank" rel="noopener noreferrer"
                  className="flex items-center justify-center gap-2 bg-green-500 text-white font-body font-medium px-5 py-3 rounded-xl hover:bg-green-600 transition-all text-sm">
                  <MessageCircle size={16} /> WhatsApp
                </a>
                <a href="https://www.instagram.com/dayspahochheim/" target="_blank" rel="noopener noreferrer"
                  className="flex items-center justify-center gap-2 bg-gradient-to-r from-purple-500 to-pink-500 text-white font-body font-medium px-5 py-3 rounded-xl hover:opacity-90 transition-all text-sm">
                  <Instagram size={16} /> Instagram
                </a>
              </div>
            </div>

            {/* Formulário */}
            <div>
              <h2 className="section-title mb-8">Envie uma mensagem</h2>

              {sent ? (
                <div className="bg-teal-50 border border-teal-200 rounded-xl3 p-8 text-center">
                  <div className="w-14 h-14 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <CheckCircle size={24} className="text-teal-600" />
                  </div>
                  <p className="font-display text-xl text-spa-dark mb-2">Mensagem enviada!</p>
                  <p className="text-sm text-spa-muted font-body">Em breve entraremos em contato.</p>
                  <button onClick={() => { setSent(false); setForm(initialForm) }}
                    className="mt-5 text-xs font-body text-spa-muted underline hover:text-spa-dark">
                    Enviar outra mensagem
                  </button>
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-4" noValidate>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-body font-medium text-spa-muted mb-1.5">Nome *</label>
                      <input className={`input-field ${errors.name ? 'border-red-400' : ''}`}
                        placeholder="Seu nome" value={form.name}
                        onChange={e => set('name', e.target.value)} autoComplete="name" />
                      {errors.name && <FieldError msg={errors.name} />}
                    </div>
                    <div>
                      <label className="block text-xs font-body font-medium text-spa-muted mb-1.5">Telefone</label>
                      <input className={`input-field ${errors.phone ? 'border-red-400' : ''}`}
                        placeholder="(47) 9 0000-0000" value={form.phone}
                        onChange={e => set('phone', formatPhone(e.target.value))}
                        inputMode="numeric" autoComplete="tel" />
                      {errors.phone && <FieldError msg={errors.phone} />}
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-body font-medium text-spa-muted mb-1.5">E-mail *</label>
                    <input type="email" className={`input-field ${errors.email ? 'border-red-400' : ''}`}
                      placeholder="seu@email.com" value={form.email}
                      onChange={e => set('email', e.target.value)} autoComplete="email" />
                    {errors.email && <FieldError msg={errors.email} />}
                  </div>

                  <div>
                    <label className="block text-xs font-body font-medium text-spa-muted mb-1.5">Assunto</label>
                    <input className="input-field" placeholder="Como podemos ajudar?"
                      value={form.subject} onChange={e => set('subject', e.target.value)} />
                  </div>

                  <div>
                    <label className="block text-xs font-body font-medium text-spa-muted mb-1.5">Mensagem *</label>
                    <textarea className={`input-field resize-none ${errors.message ? 'border-red-400' : ''}`}
                      rows={5} placeholder="Escreva sua mensagem..."
                      value={form.message} onChange={e => set('message', e.target.value)} />
                    {errors.message && <FieldError msg={errors.message} />}
                  </div>

                  {apiError && (
                    <div className="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-body">
                      <AlertCircle size={14} className="shrink-0" /> {apiError}
                    </div>
                  )}

                  <button type="submit" disabled={loading}
                    className="btn-primary w-full flex items-center justify-center gap-2 py-4 disabled:opacity-60">
                    {loading ? (
                      <>
                        <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                        </svg>
                        Enviando...
                      </>
                    ) : (
                      <><Send size={16} /> Enviar mensagem</>
                    )}
                  </button>
                </form>
              )}
            </div>
          </div>
        </div>
      </section>
    </div>
  )
}

function FieldError({ msg }) {
  return (
    <p className="flex items-center gap-1 text-red-500 text-xs font-body mt-1">
      <AlertCircle size={11} /> {msg}
    </p>
  )
}
