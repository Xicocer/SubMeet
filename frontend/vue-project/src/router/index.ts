import { createRouter, createWebHistory } from 'vue-router'
import { pinia } from '@/pinia'
import { useAuthStore } from '@/stores/auth'
import AdminDashboardView from '@/views/AdminDashboardView.vue'
import AdminDictionariesView from '@/views/AdminDictionariesView.vue'
import AdminEventsView from '@/views/AdminEventsView.vue'
import AdminIncidentsView from '@/views/AdminIncidentsView.vue'
import AdminOrganizersView from '@/views/AdminOrganizersView.vue'
import EventDetailsView from '@/views/EventDetailsView.vue'
import EventConciergeView from '@/views/EventConciergeView.vue'
import EventsView from '@/views/EventsView.vue'
import CheckoutView from '@/views/CheckoutView.vue'
import LoginView from '@/views/LoginView.vue'
import OrganizerDashboardView from '@/views/OrganizerDashboardView.vue'
import OrganizerEventsView from '@/views/OrganizerEventsView.vue'
import OrganizerHallLibraryView from '@/views/OrganizerHallLibraryView.vue'
import OrganizerHallsView from '@/views/OrganizerHallsView.vue'
import OrganizerRegisterView from '@/views/OrganizerRegisterView.vue'
import OrganizerTicketVerificationView from '@/views/OrganizerTicketVerificationView.vue'
import ProfileView from '@/views/ProfileView.vue'
import RegisterView from '@/views/RegisterView.vue'
import VenueRegisterView from '@/views/VenueRegisterView.vue'

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
      path: '/assistant',
      name: 'event-concierge',
      component: EventConciergeView,
      meta: { requiresAuth: true },
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
      path: '/register/organizer',
      name: 'organizer-register',
      component: OrganizerRegisterView,
      meta: { publicOnly: true },
    },
    {
      path: '/register/venue',
      name: 'venue-register',
      component: VenueRegisterView,
      meta: { publicOnly: true },
    },
    {
      path: '/profile',
      name: 'profile',
      component: ProfileView,
      meta: { requiresAuth: true },
    },
    {
      path: '/checkout/:id',
      name: 'checkout',
      component: CheckoutView,
      meta: { immersive: true },
    },
    {
      path: '/admin/dashboard',
      name: 'admin-dashboard',
      component: AdminDashboardView,
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/organizers',
      name: 'admin-organizers',
      component: AdminOrganizersView,
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/events',
      name: 'admin-events',
      component: AdminEventsView,
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/incidents',
      name: 'admin-incidents',
      component: AdminIncidentsView,
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/admin/dictionaries',
      name: 'admin-dictionaries',
      component: AdminDictionariesView,
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/organizer/dashboard',
      name: 'organizer-dashboard',
      component: OrganizerDashboardView,
      meta: { requiresAuth: true, requiresOrganizer: true },
    },
    {
      path: '/organizer/events',
      name: 'organizer-events',
      component: OrganizerEventsView,
      meta: { requiresAuth: true, requiresOrganizer: true },
    },
    {
      path: '/organizer/tickets',
      name: 'organizer-tickets',
      component: OrganizerTicketVerificationView,
      meta: { requiresAuth: true, requiresOrganizer: true },
    },
    {
      path: '/venue/halls',
      name: 'venue-halls',
      component: OrganizerHallLibraryView,
      meta: { requiresAuth: true, requiresVenueOwner: true },
    },
    {
      path: '/venue/halls/new',
      name: 'venue-hall-create',
      component: OrganizerHallsView,
      meta: { requiresAuth: true, requiresVenueOwner: true, immersive: true },
    },
    {
      path: '/venue/halls/:id/edit',
      name: 'venue-hall-edit',
      component: OrganizerHallsView,
      meta: { requiresAuth: true, requiresVenueOwner: true, immersive: true },
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

  if (to.meta.requiresVenueOwner && !authStore.isVenueOwner) {
    return { name: 'events' }
  }

  if (to.meta.requiresAdmin && !authStore.isAdmin) {
    return { name: 'events' }
  }

  return true
})

export default router
