import { useState } from 'react'
import { Eye, EyeOff, Lock, Loader2, AlertCircle } from 'lucide-react'
import { adminApi, ApiError } from '../../services/api'

export default function AdminLogin({ onLogin }) {
  const [form, setForm]     = useState({ username: '', password: '' })
  const [showPass, setShowPass] = useState(false)
  const [loading, setLoading]  = useState(false)
  const [error, setError]      = useState('')

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')

    if (!form.username.trim() || !form.password) {
      setError('Preencha usuário e senha.')
      return
    }

    setLoading(true)
    try {
      await adminApi.login(form.username.trim(), form.password)
      // Persiste credenciais na sessão (limpas ao fechar o navegador)
      sessionStorage.setItem('adminCredentials', btoa(`${form.username.trim()}:${form.password}`))
      onLogin()
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        setError('Usuário ou senha incorretos.')
      } else {
        setError(err.message ?? 'Falha na autenticação.')
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-spa-dark flex items-center justify-center p-6">
      <div className="w-full max-w-sm">
        {/* Logo */}
        <div className="text-center mb-10">
          <div className="w-14 h-14 bg-spa-accent/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <Lock size={24} className="text-spa-accent" />
          </div>
          <h1 className="font-display text-2xl text-white font-bold">Day Spa Hochheim</h1>
          <p className="text-sm text-white/50 font-body mt-1">Painel Administrativo</p>
        </div>

        <form onSubmit={handleSubmit} className="bg-white/5 backdrop-blur border border-white/10 rounded-2xl p-8 space-y-5" noValidate>
          <div>
            <label className="block text-xs font-body font-medium text-white/60 mb-2 uppercase tracking-wider">
              Usuário
            </label>
            <input
              type="text"
              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/30 font-body text-sm focus:outline-none focus:border-spa-accent transition-colors"
              placeholder="admin"
              value={form.username}
              onChange={e => setForm(f => ({ ...f, username: e.target.value }))}
              autoComplete="username"
              autoFocus
            />
          </div>

          <div>
            <label className="block text-xs font-body font-medium text-white/60 mb-2 uppercase tracking-wider">
              Senha
            </label>
            <div className="relative">
              <input
                type={showPass ? 'text' : 'password'}
                className="w-full px-4 py-3 pr-11 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/30 font-body text-sm focus:outline-none focus:border-spa-accent transition-colors"
                placeholder="••••••••"
                value={form.password}
                onChange={e => setForm(f => ({ ...f, password: e.target.value }))}
                autoComplete="current-password"
              />
              <button type="button" onClick={() => setShowPass(s => !s)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/70 transition-colors">
                {showPass ? <EyeOff size={16} /> : <Eye size={16} />}
              </button>
            </div>
          </div>

          {error && (
            <div className="flex items-center gap-2 p-3 bg-red-500/20 border border-red-500/30 rounded-xl text-sm text-red-300 font-body">
              <AlertCircle size={14} className="shrink-0" /> {error}
            </div>
          )}

          <button type="submit" disabled={loading}
            className="w-full flex items-center justify-center gap-2 bg-spa-accent text-spa-dark font-body font-semibold py-3 rounded-xl hover:bg-spa-accent/90 transition-all disabled:opacity-60 disabled:cursor-not-allowed">
            {loading ? <Loader2 size={16} className="animate-spin" /> : <Lock size={16} />}
            {loading ? 'Autenticando...' : 'Entrar'}
          </button>
        </form>

        <p className="text-center text-xs text-white/30 font-body mt-6">
          Credenciais gerenciadas via .htpasswd do cPanel
        </p>
      </div>
    </div>
  )
}
