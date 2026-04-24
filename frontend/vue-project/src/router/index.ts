import { createRouter, createWebHistory } from 'vue-router'
import { pinia } from '@/pinia'
import { useAuthStore } from '@/stores/auth'
import EventDetailsView from '@/views/EventDetailsView.vue'
import EventsView from '@/views/EventsView.vue'
import LoginView from '@/views/LoginView.vue'
import OrganizerEventsView from '@/views/OrganizerEventsView.vue'
import ProfileView from '@/views/ProfileView.vue'
import RegisterView from '@/views/RegisterView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/events',
    },
    {
      path: '/events',
      name: 'events',
      component: EventsView,
    },
    {
      path: '/events/:id',
      name: 'event-details',
      component: EventDetailsView,
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { publicOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterView,
      meta: { publicOnly: true },
    },
    {
      path: '/profile',
      name: 'profile',
      component: ProfileView,
      meta: { requiresAuth: true },
    },
    {
      path: '/organizer/events',
      name: 'organizer-events',
      component: OrganizerEventsView,
      meta: { requiresAuth: true, requiresOrganizer: true },
    },
  ],
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore(pinia)
  let hasToken = Boolean(authStore.token || localStorage.getItem('token'))

  if (hasToken && !authStore.user) {
    await authStore.fetchMe()
    hasToken = authStore.isAuthenticated
  }

  if (to.meta.publicOnly && hasToken) {
    return { name: 'events' }
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return {
      name: 'login',
      query: { redirect: to.fullPath },
    }
  }

  if (to.meta.requiresOrganizer && !authStore.isOrganizer) {
    return { name: 'events' }
  }

  return true
})

export default router
