/**
 * Cliente HTTP centralizado para a API do Day Spa Hochheim.
 *
 * Princípios de segurança:
 * - Dados de cartão NUNCA passam por este cliente — ficam com o Mercado Pago
 * - Toda comunicação é via HTTPS (configurado no cPanel/servidor)
 * - Tokens de admin ficam em sessionStorage e são enviados via Basic Auth
 * - Respostas com 401 disparam logout automático no admin
 */

const BASE_URL = import.meta.env.VITE_API_URL ?? '/api'

// ─── Classe de erro da API ────────────────────────────────────────────────────

export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message)
    this.name    = 'ApiError'
    this.status  = status
    this.errors  = errors
  }
}

// ─── Requisição base ─────────────────────────────────────────────────────────

async function request(method, path, body = null, extraHeaders = {}) {
  const headers = {
    'Content-Type': 'application/json',
    'Accept':       'application/json',
    ...extraHeaders,
  }

  const opts = { method, headers }
  if (body !== null) opts.body = JSON.stringify(body)

  let response
  try {
    response = await fetch(`${BASE_URL}${path}`, opts)
  } catch (err) {
    throw new ApiError(
      'Falha de conexão. Verifique sua internet e tente novamente.',
      0
    )
  }

  // Sem conteúdo
  if (response.status === 204) return { success: true }

  // Lê como texto primeiro — se não for JSON, loga o conteúdo real no console
  const rawText = await response.text().catch(() => '')

  let data
  try {
    data = JSON.parse(rawText)
  } catch {
    console.error(
      `[API] Resposta não-JSON de ${method} ${path} (HTTP ${response.status}):\n`,
      rawText.slice(0, 2000)
    )
    throw new ApiError('Resposta inválida do servidor.', response.status)
  }

  if (!response.ok) {
    // 401 no admin → dispara logout
    if (response.status === 401 && path.startsWith('/admin')) {
      sessionStorage.removeItem('adminCredentials')
      window.dispatchEvent(new CustomEvent('admin:logout'))
    }
    throw new ApiError(
      data.message ?? 'Erro na requisição.',
      response.status,
      data.errors ?? {}
    )
  }

  return data
}

// ─── Endpoints públicos ───────────────────────────────────────────────────────

export const api = {
  /** Lista todos os serviços ativos. */
  getServices() {
    return request('GET', '/services')
  },

  /** Detalhe de um serviço. */
  getService(id) {
    return request('GET', `/services/${id}`)
  },

  /**
   * Cria pedido completo.
   * @param {{ customer: object, items: object[], gift_card_format: string }} payload
   */
  createOrder(payload) {
    return request('POST', '/orders', payload)
  },

  /**
   * Cria preferência de pagamento no Mercado Pago.
   * Retorna { preference_id, init_point, sandbox_init_point }
   */
  createPaymentPreference(orderId) {
    return request('POST', '/payments/create-preference', { order_id: orderId })
  },

  /**
   * Valida cartão presente pelo código (usado na página do QR Code).
   * @param {string} code  Ex: SPA-5AB3W2
   */
  validateGiftCard(code) {
    return request('GET', `/giftcards/${encodeURIComponent(code)}`)
  },

  /**
   * Envia formulário de contato.
   */
  sendContact(payload) {
    return request('POST', '/contact', payload)
  },

  /**
   * Simula pagamento aprovado — apenas APP_ENV=development no backend.
   * Dispara o fluxo completo: Gift Card + e-mail + NFS-e.
   */
  mockApprovePayment(orderId) {
    return request('POST', '/payments/mock-approve', { order_id: orderId })
  },
}

// ─── Endpoints admin (requerem Basic Auth) ────────────────────────────────────

function adminCredentials() {
  const stored = sessionStorage.getItem('adminCredentials')
  if (!stored) throw new ApiError('Não autenticado.', 401)
  return { Authorization: `Basic ${stored}` }
}

export const adminApi = {
  // ── Autenticação ──────────────────────────────────────────────────────────

  /** Testa credenciais — retorna dados do dashboard se válido. */
  login(username, password) {
    const credentials = btoa(`${username}:${password}`)
    return request('GET', '/admin/dashboard', null, {
      Authorization: `Basic ${credentials}`,
    })
  },

  // ── Dashboard ─────────────────────────────────────────────────────────────

  getDashboard() {
    return request('GET', '/admin/dashboard', null, adminCredentials())
  },

  // ── Serviços ──────────────────────────────────────────────────────────────

  getServices() {
    return request('GET', '/admin/services', null, adminCredentials())
  },

  createService(data) {
    return request('POST', '/admin/services', data, adminCredentials())
  },

  updateService(id, data) {
    return request('PUT', `/admin/services/${id}`, data, adminCredentials())
  },

  deleteService(id) {
    return request('DELETE', `/admin/services/${id}`, null, adminCredentials())
  },

  uploadServiceImage(id, file) {
    const creds = adminCredentials()
    const formData = new FormData()
    formData.append('image', file)
    return fetch(`${BASE_URL}/admin/services/${id}/image`, {
      method: 'POST',
      headers: { Authorization: creds.Authorization },
      body: formData,
    }).then(r => r.json())
  },

  // ── Pedidos ───────────────────────────────────────────────────────────────

  getOrders(params = {}) {
    const qs = new URLSearchParams(params).toString()
    return request('GET', `/admin/orders${qs ? '?' + qs : ''}`, null, adminCredentials())
  },

  getOrder(id) {
    return request('GET', `/admin/orders/${id}`, null, adminCredentials())
  },

  resendEmail(orderId) {
    return request('POST', `/admin/orders/${orderId}/resend-email`, null, adminCredentials())
  },

  // ── Cartões Presente ──────────────────────────────────────────────────────

  getGiftCards(params = {}) {
    const qs = new URLSearchParams(params).toString()
    return request('GET', `/admin/giftcards${qs ? '?' + qs : ''}`, null, adminCredentials())
  },

  redeemGiftCard(code, data) {
    return request('POST', `/admin/giftcards/${encodeURIComponent(code)}/redeem`, data, adminCredentials())
  },

  // ── Notas Fiscais ─────────────────────────────────────────────────────────

  getInvoices(params = {}) {
    const qs = new URLSearchParams(params).toString()
    return request('GET', `/admin/invoices${qs ? '?' + qs : ''}`, null, adminCredentials())
  },

  reissueInvoice(id) {
    return request('POST', `/admin/invoices/${id}/reissue`, null, adminCredentials())
  },

  // ── Relatórios ────────────────────────────────────────────────────────────

  getRevenueReport(months = 12) {
    return request('GET', `/admin/reports/revenue?months=${months}`, null, adminCredentials())
  },

  getServicesReport(limit = 10) {
    return request('GET', `/admin/reports/services?limit=${limit}`, null, adminCredentials())
  },
}
