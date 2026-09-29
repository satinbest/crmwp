import axios from 'axios';

let csrfToken = null;

export const setCsrfToken = (token) => {
  csrfToken = token;
};

export const getCsrfToken = () => csrfToken;

const apiClient = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
  timeout: 15000,
});

apiClient.interceptors.request.use((config) => {
  if (csrfToken && ['post', 'put', 'patch', 'delete'].includes(config.method?.toLowerCase() || '')) {
    config.headers['X-CSRF-TOKEN'] = csrfToken;
  }
  const activeStoreId = localStorage.getItem('crmwp_active_store_id');
  if (activeStoreId && !config.headers['X-Store-Id']) {
    config.headers['X-Store-Id'] = activeStoreId;
  }
  return config;
}, (error) => {
  return Promise.reject(error);
});

apiClient.interceptors.response.use(
  (response) => {
    // If backend returns a new CSRF token in payload, keep it synced
    if (response.data?.data?.csrf_token) {
      csrfToken = response.data.data.csrf_token;
    }
    return response.data;
  },
  (error) => {
    const errorResponse = error.response?.data?.error || {
      code: 'NETWORK_ERROR',
      message: 'خطا در ارتباط با سرور. لطفاً اتصال اینترنت یا وضعیت سرور را بررسی کنید.',
      details: {},
    };

    if (error.response?.status === 401) {
      // Unauthenticated, broadcast or trigger redirect
      window.dispatchEvent(new CustomEvent('auth:unauthorized'));
    }

    return Promise.reject(errorResponse);
  }
);

export default apiClient;
