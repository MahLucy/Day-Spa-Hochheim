import { useEffect, useState, useRef } from 'react'
import { Plus, Pencil, Trash2, ToggleLeft, ToggleRight, Loader2, X, Upload, AlertCircle, Check } from 'lucide-react'
import { adminApi, ApiError } from '../../services/api'

const CATEGORIES = ['Spa', 'Massagem', 'Massagem Spa Terapia', 'Drenagem Linfática', 'Banho', 'Cuidados Especiais', 'Depilação']
const emptyForm  = { name: '', category: CATEGORIES[0], description: '', price: '', duration_minutes: '60', sessions: '1', active: true }

export default function AdminServices() {
  const [services, setServices] = useState([])
  const [loading, setLoading]   = useState(true)
  const [error, setError]       = useState('')
  const [modal, setModal]       = useState(null)  // null | { mode: 'create'|'edit', data: {} }
  const [saving, setSaving]     = useState(false)
  const [form, setForm]         = useState(emptyForm)
  const [formErrors, setFormErrors] = useState({})
  const [toast, setToast]       = useState('')

  const load = async () => {
    setLoading(true)
    try {
      const res = await adminApi.getServices()
      setServices(res.data ?? [])
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao carregar serviços.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [])

  const showToast = (msg) => {
    setToast(msg)
    setTimeout(() => setToast(''), 3000)
  }

  const openCreate = () => {
    setForm(emptyForm)
    setFormErrors({})
    setModal({ mode: 'create' })
  }

  const openEdit = (service) => {
    setForm({
      name:             service.name,
      category:         service.category,
      description:      service.description ?? '',
      price:            service.price,
      duration_minutes: String(service.duration_minutes),
      sessions:         String(service.sessions),
      active:           !!service.active,
    })
    setFormErrors({})
    setModal({ mode: 'edit', id: service.id })
  }

  const validateForm = () => {
    const e = {}
    if (!form.name.trim())  e.name  = 'Nome obrigatório.'
    if (!form.price || isNaN(Number(form.price)) || Number(form.price) <= 0) e.price = 'Preço inválido.'
    if (!form.duration_minutes || Number(form.duration_minutes) <= 0) e.duration_minutes = 'Duração inválida.'
    return e
  }

  const handleSave = async (e) => {
    e.preventDefault()
    const errs = validateForm()
    if (Object.keys(errs).length) { setFormErrors(errs); return }

    setSaving(true)
    try {
      const payload = {
        ...form,
        price:            parseFloat(form.price),
        duration_minutes: parseInt(form.duration_minutes),
        sessions:         parseInt(form.sessions),
        active:           form.active ? 1 : 0,
      }

      if (modal.mode === 'create') {
        await adminApi.createService(payload)
        showToast('Serviço criado com sucesso.')
      } else {
        await adminApi.updateService(modal.id, payload)
        showToast('Serviço atualizado.')
      }

      setModal(null)
      await load()
    } catch (err) {
      if (err instanceof ApiError && err.errors) {
        setFormErrors(err.errors)
      } else {
        setFormErrors({ _global: err.message })
      }
    } finally {
      setSaving(false)
    }
  }

  const handleToggleActive = async (service) => {
    try {
      await adminApi.updateService(service.id, { active: service.active ? 0 : 1 })
      showToast(service.active ? 'Serviço desativado.' : 'Serviço ativado.')
      await load()
    } catch (err) {
      showToast('Erro: ' + err.message)
    }
  }

  const handleDelete = async (service) => {
    if (!confirm(`Desativar "${service.name}"? O serviço não será excluído, apenas desativado.`)) return
    try {
      await adminApi.deleteService(service.id)
      showToast('Serviço desativado.')
      await load()
    } catch (err) {
      showToast('Erro: ' + err.message)
    }
  }

  const handleImageUpload = async (id, event) => {
    const file = event.target.files?.[0]
    if (!file) return
    
    showToast('Enviando imagem...')
    
    try {
      await adminApi.uploadServiceImage(id, file)
      showToast('Imagem atualizada com sucesso.')
      await load()
    } catch (err) {
      showToast('Erro ao enviar imagem: ' + err.message)
    } finally {
      event.target.value = ''
    }
  }

  return (
    <div>
      {/* Toast */}
      {toast && (
        <div className="fixed top-6 right-6 z-50 flex items-center gap-2 bg-spa-dark text-white text-sm font-body px-4 py-3 rounded-xl shadow-lg">
          <Check size={14} /> {toast}
        </div>
      )}

      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="font-display text-2xl lg:text-3xl text-spa-dark font-bold">Serviços</h1>
          <p className="text-sm text-spa-muted font-body mt-1">{services.length} serviços cadastrados</p>
        </div>
        <button onClick={openCreate}
          className="flex items-center gap-2 bg-spa-dark text-white text-sm font-body font-medium px-4 py-2.5 rounded-xl hover:bg-spa-mid transition-all">
          <Plus size={15} /> Novo serviço
        </button>
      </div>

      {loading ? (
        <div className="flex justify-center py-16"><Loader2 size={28} className="animate-spin text-spa-light" /></div>
      ) : error ? (
        <div className="flex items-center gap-2 p-4 bg-red-50 rounded-xl text-red-700 text-sm font-body">
          <AlertCircle size={16} /> {error}
        </div>
      ) : (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-gray-100">
                  <th className="text-left text-xs font-body font-semibold text-spa-muted uppercase tracking-wider px-6 py-4">Serviço</th>
                  <th className="text-left text-xs font-body font-semibold text-spa-muted uppercase tracking-wider px-4 py-4">Categoria</th>
                  <th className="text-left text-xs font-body font-semibold text-spa-muted uppercase tracking-wider px-4 py-4">Preço</th>
                  <th className="text-left text-xs font-body font-semibold text-spa-muted uppercase tracking-wider px-4 py-4">Duração</th>
                  <th className="text-center text-xs font-body font-semibold text-spa-muted uppercase tracking-wider px-4 py-4">Status</th>
                  <th className="text-right px-6 py-4"></th>
                </tr>
              </thead>
              <tbody>
                {services.map(s => (
                  <tr key={s.id} className="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        {s.image_url ? (
                          <img src={s.image_url} alt={s.name} className="w-10 h-10 rounded-lg object-cover shrink-0" />
                        ) : (
                          <div className="w-10 h-10 rounded-lg bg-spa-pale flex items-center justify-center shrink-0 text-spa-light text-xs font-bold">
                            {s.name[0]}
                          </div>
                        )}
                        <div>
                          <p className="text-sm font-body font-medium text-spa-dark">{s.name}</p>
                          {s.description && (
                            <p className="text-xs font-body text-spa-muted truncate max-w-[200px]">{s.description}</p>
                          )}
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-4">
                      <CategoryBadge category={s.category} />
                    </td>
                    <td className="px-4 py-4">
                      <span className="text-sm font-body font-semibold text-spa-dark">
                        R$ {Number(s.price).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                      </span>
                    </td>
                    <td className="px-4 py-4">
                      <span className="text-sm font-body text-spa-muted">{s.duration_minutes} min</span>
                    </td>
                    <td className="px-4 py-4 text-center">
                      <button onClick={() => handleToggleActive(s)} className="inline-flex items-center">
                        {s.active
                          ? <ToggleRight size={22} className="text-green-500" />
                          : <ToggleLeft size={22} className="text-gray-300" />
                        }
                      </button>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <label className="cursor-pointer p-2 rounded-lg hover:bg-gray-100 transition-all" title="Upload de imagem">
                          <Upload size={14} className="text-spa-muted" />
                          <input type="file" accept="image/jpeg,image/png,image/webp" className="hidden"
                            onChange={(e) => handleImageUpload(s.id, e)} />
                        </label>
                        <button onClick={() => openEdit(s)}
                          className="p-2 rounded-lg hover:bg-spa-pale transition-all" title="Editar">
                          <Pencil size={14} className="text-spa-muted" />
                        </button>
                        <button onClick={() => handleDelete(s)}
                          className="p-2 rounded-lg hover:bg-red-50 transition-all" title="Desativar">
                          <Trash2 size={14} className="text-red-400" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Modal criar/editar */}
      {modal && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between p-6 border-b border-gray-100">
              <h2 className="font-display text-xl text-spa-dark font-bold">
                {modal.mode === 'create' ? 'Novo Serviço' : 'Editar Serviço'}
              </h2>
              <button onClick={() => setModal(null)} className="p-2 rounded-lg hover:bg-gray-100 transition-all">
                <X size={18} className="text-spa-muted" />
              </button>
            </div>

            <form onSubmit={handleSave} className="p-6 space-y-4" noValidate>
              {formErrors._global && (
                <div className="flex items-center gap-2 p-3 bg-red-50 rounded-xl text-red-700 text-sm font-body">
                  <AlertCircle size={14} /> {formErrors._global}
                </div>
              )}

              <FormField label="Nome *" error={formErrors.name}>
                <input className="input-field" value={form.name}
                  onChange={e => setForm(f => ({ ...f, name: e.target.value }))} placeholder="Nome do serviço" />
              </FormField>

              <FormField label="Categoria" error={null}>
                <select className="input-field" value={form.category}
                  onChange={e => setForm(f => ({ ...f, category: e.target.value }))}>
                  {CATEGORIES.map(c => <option key={c} value={c}>{c}</option>)}
                </select>
              </FormField>

              <FormField label="Descrição" error={null}>
                <textarea className="input-field resize-none" rows={3} value={form.description}
                  onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
                  placeholder="Descrição do serviço" />
              </FormField>

              <div className="grid grid-cols-3 gap-3">
                <FormField label="Preço (R$) *" error={formErrors.price}>
                  <input type="number" step="0.01" min="0" className="input-field" value={form.price}
                    onChange={e => setForm(f => ({ ...f, price: e.target.value }))} placeholder="0,00" />
                </FormField>
                <FormField label="Duração (min) *" error={formErrors.duration_minutes}>
                  <input type="number" min="1" className="input-field" value={form.duration_minutes}
                    onChange={e => setForm(f => ({ ...f, duration_minutes: e.target.value }))} />
                </FormField>
                <FormField label="Sessões" error={null}>
                  <input type="number" min="1" className="input-field" value={form.sessions}
                    onChange={e => setForm(f => ({ ...f, sessions: e.target.value }))} />
                </FormField>
              </div>

              <label className="flex items-center gap-3 cursor-pointer">
                <div className={`w-5 h-5 rounded border-2 flex items-center justify-center transition-all ${
                  form.active ? 'bg-spa-dark border-spa-dark' : 'border-gray-300'
                }`}>
                  {form.active && <Check size={12} className="text-white" strokeWidth={3} />}
                </div>
                <input type="checkbox" className="sr-only" checked={form.active}
                  onChange={e => setForm(f => ({ ...f, active: e.target.checked }))} />
                <span className="text-sm font-body text-spa-muted">Serviço ativo (visível no site)</span>
              </label>

              <div className="flex gap-3 pt-2">
                <button type="button" onClick={() => setModal(null)}
                  className="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-body text-spa-muted hover:bg-gray-50 transition-all">
                  Cancelar
                </button>
                <button type="submit" disabled={saving}
                  className="flex-1 flex items-center justify-center gap-2 py-3 bg-spa-dark text-white text-sm font-body font-medium rounded-xl hover:bg-spa-mid transition-all disabled:opacity-60">
                  {saving ? <Loader2 size={14} className="animate-spin" /> : <Check size={14} />}
                  {saving ? 'Salvando...' : 'Salvar'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}

function CategoryBadge({ category }) {
  const colors = {
    'Spa':                  'bg-teal-50 text-teal-700',
    'Massagem':             'bg-purple-50 text-purple-700',
    'Massagem Spa Terapia': 'bg-pink-50 text-pink-700',
    'Drenagem Linfática':   'bg-blue-50 text-blue-700',
    'Banho':                'bg-sky-50 text-sky-700',
    'Cuidados Especiais':   'bg-amber-50 text-amber-700',
    'Depilação':            'bg-orange-50 text-orange-700',
  }
  return (
    <span className={`text-xs font-body font-medium px-2.5 py-1 rounded-full ${colors[category] ?? 'bg-gray-100 text-gray-600'}`}>
      {category}
    </span>
  )
}

function FormField({ label, error, children }) {
  return (
    <div>
      <label className="block text-xs font-body font-semibold text-spa-muted mb-1.5">{label}</label>
      {children}
      {error && <p className="text-red-500 text-xs font-body mt-1">{error}</p>}
    </div>
  )
}
