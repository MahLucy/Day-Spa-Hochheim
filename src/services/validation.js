/**
 * Validadores client-side — espelham o backend (PHP Validator.php).
 * CPF usa o mesmo algoritmo dígito verificador.
 */

// ─── CPF ─────────────────────────────────────────────────────────────────────

export function validateCpf(cpf) {
  const digits = cpf.replace(/\D/g, '')
  if (digits.length !== 11) return false
  if (/^(\d)\1{10}$/.test(digits)) return false          // todos iguais

  for (let t = 9; t < 11; t++) {
    let d = 0
    for (let c = 0; c < t; c++) {
      d += parseInt(digits[c]) * ((t + 1) - c)
    }
    d = ((10 * d) % 11) % 10
    if (parseInt(digits[t]) !== d) return false
  }
  return true
}

export function formatCpf(value) {
  const digits = value.replace(/\D/g, '').slice(0, 11)
  return digits
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4')
}

// ─── Telefone ────────────────────────────────────────────────────────────────

export function validatePhone(phone) {
  const digits = phone.replace(/\D/g, '')
  return digits.length >= 10 && digits.length <= 11
}

export function formatPhone(value) {
  const digits = value.replace(/\D/g, '').slice(0, 11)
  if (digits.length <= 10) {
    return digits
      .replace(/(\d{2})(\d)/, '($1) $2')
      .replace(/(\d{4})(\d)/, '$1-$2')
  }
  return digits
    .replace(/(\d{2})(\d)/, '($1) $2')
    .replace(/(\d{5})(\d)/, '$1-$2')
}

// ─── E-mail ──────────────────────────────────────────────────────────────────

export function validateEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.trim())
}

// ─── Data (dd/mm/aaaa) ───────────────────────────────────────────────────────

export function formatDate(value) {
  const digits = value.replace(/\D/g, '').slice(0, 8)
  return digits
    .replace(/(\d{2})(\d)/, '$1/$2')
    .replace(/(\d{2})\/(\d{2})(\d)/, '$1/$2/$3')
}

export function validateDate(value) {
  const match = value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/)
  if (!match) return false
  const [, day, month, year] = match.map(Number)
  const date = new Date(year, month - 1, day)
  return (
    date.getFullYear() === year &&
    date.getMonth() === month - 1 &&
    date.getDate() === day
  )
}

// ─── Helper genérico de formulário ───────────────────────────────────────────

/**
 * Sanitiza uma string (remove tags HTML).
 * React já escapa por padrão, mas útil antes de enviar à API.
 */
export function sanitize(str) {
  return String(str).replace(/<[^>]*>/g, '').trim()
}
