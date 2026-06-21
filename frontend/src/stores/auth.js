import { defineStore } from 'pinia';
import axios from 'axios';

const baseURL = import.meta.env.VITE_API_BASE_URL;

export const useAuth = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('token') || null,
    user: JSON.parse(localStorage.getItem('user') || 'null'),
  }),

  getters: {
    isAuthenticated: (s) => !!s.token,
    isAdmin: (s) => s.user?.role === 'admin',
  },

  actions: {
    async login(email, password) {
      try {
        // 1. 登录获取 token
        const { data } = await axios.post(`${baseURL}/auth/login`, { email, password });
        this.token = data.access_token;
        localStorage.setItem('token', this.token);

        // 2. ✅ 请求用户信息（获取真实 role）
        const { data: userData } = await axios.get(`${baseURL}/auth/me`, {
          headers: { Authorization: `Bearer ${data.access_token}` }
        });
        this.user = userData;
        localStorage.setItem('user', JSON.stringify(this.user));

        console.log('✅ 登录成功:', this.user);

      } catch (e) {
        console.error('❌ 登录失败:', e);
        throw e;
      }
    },

    async register(name, email, password) {
      await axios.post(`${baseURL}/auth/register`, { name, email, password });
      await this.login(email, password);
    },

    logout() {
      this.token = null;
      this.user = null;
      localStorage.removeItem('token');
      localStorage.removeItem('user');
    },
  },
});
