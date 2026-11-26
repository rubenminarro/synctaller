import { AxiosError, AxiosHeaders, type InternalAxiosRequestConfig } from 'axios'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { tenantStatus } from '@/lib/tenantStatus'
import { tokenStorage } from '@/lib/tokenStorage'
import { api, getData, isApiError } from '@/services/api'

const originalAdapter = api.defaults.adapter

/** La API responde `status` con el sobre de error y `error_code`, sin red. */
function respondWith(status: number, errorCode: string, message: string): void {
  api.defaults.adapter = (config: InternalAxiosRequestConfig) => {
    const response = {
      status,
      statusText: '',
      headers: new AxiosHeaders(),
      config,
      data: { success: false, status, message, data: null, errors: null, error_code: errorCode },
    }
    return Promise.reject(new AxiosError(message, 'ERR_BAD_REQUEST', config, null, response))
  }
}

async function failure(): Promise<unknown> {
  try {
    await getData('/branding')
  } catch (error) {
    return error
  }
  throw new Error('El request debía fallar.')
}

describe('cliente HTTP ante un taller suspendido', () => {
  beforeEach(() => {
    tenantStatus.reset()
    tokenStorage.clear()
  })

  afterEach(() => {
    api.defaults.adapter = originalAdapter
    tenantStatus.reset()
    tokenStorage.clear()
  })

  it('marca el taller suspendido y conserva el token', async () => {
    tokenStorage.set('token-vigente')
    respondWith(403, 'TENANT_SUSPENDED', 'El taller está suspendido.')

    const error = await failure()

    expect(isApiError(error) && error.code).toBe('TENANT_SUSPENDED')
    expect(tenantStatus.isSuspended()).toBe(true)
    expect(tokenStorage.get()).toBe('token-vigente')
  })

  it('no confunde un 403 de permisos con la suspensión', async () => {
    respondWith(403, 'FORBIDDEN', 'No tiene permiso para realizar esta acción.')

    await failure()

    expect(tenantStatus.isSuspended()).toBe(false)
  })

  it('no trata como suspendido a un taller dado de baja (404)', async () => {
    respondWith(404, 'TENANT_NOT_FOUND', 'No se encontró el taller.')

    await failure()

    expect(tenantStatus.isSuspended()).toBe(false)
  })
})
