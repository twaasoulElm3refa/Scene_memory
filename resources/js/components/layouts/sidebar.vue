<template>
  <aside class="w-64 bg-white shadow-lg min-h-screen fixed left-0 top-0">
    <div class="p-6 border-b">
      <h2 class="text-xl font-bold text-green-600">Admin Panel</h2>
    </div>

    <nav class="p-4 space-y-2">
      <a
        v-for="item in links"
        :key="item.label"
        :href="item.href"
        class="block px-4 py-2 rounded-lg transition"
        :class="isActive(item.route)
          ? 'bg-green-100 font-bold text-green-700'
          : 'hover:bg-green-100'"
      >
        <i :class="item.icon + ' mr-2'"></i>
        {{ item.label }}
      </a>
    </nav>
  </aside>
</template>

<script>
export default {
  name: 'AdminSidebar',

  data() {
    return {
      links: [
        {
          label: 'Dashboard',
          href: '/admin',
          route: 'admin.index',
          icon: 'fas fa-chart-line',
        },
        {
          label: 'Users',
          href: '/admin/users',
          route: 'admin.users',
          icon: 'fas fa-users',
        },
        {
          label: 'Contacts',
          href: '/admin/contacts',
          route: 'admin.contacts',
          icon: 'fas fa-envelope',
        },
      ],
    }
  },

  methods: {
    isActive(routeName) {
      return document.body.dataset.route === routeName
    },
  },
}
</script>
