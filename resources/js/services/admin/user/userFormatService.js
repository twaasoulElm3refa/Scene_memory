export const userFormatService = {
  getInitials(name) {
    if (!name) return "?";
    const parts = name.split(" ");
    if (parts.length >= 2) return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
    return name.substring(0, 2).toUpperCase();
  },

  formatYear(date) {
    return date ? new Date(date).getFullYear() : new Date().getFullYear();
  },

  formatDate(date) {
    if (!date) return "Mar 12, 1990";
    return new Date(date).toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  },

  formatLastLogin(date) {
    if (!date) return "2h ago";
    const now = new Date();
    const login = new Date(date);
    const diff = Math.floor((now - login) / 1000);

    if (diff < 60) return "just now";
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return `${Math.floor(diff / 604800)}w ago`;
  },

  getRoleTitle(role) {
    const titles = {
      admin: "Creative Director",
      editor: "Senior UX Designer",
      viewer: "Lead Photographer",
      user: "Project Manager",
    };
    return titles[role] || "Team Member";
  },

  getRoleBadgeClass(role) {
    const classes = {
      admin: "role-admin",
      editor: "role-editor",
      viewer: "role-viewer",
      user: "role-user",
    };
    return classes[role] || "role-user";
  },
};