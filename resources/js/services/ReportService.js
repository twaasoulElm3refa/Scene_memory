import api from "./ApiClient";

export const ReportService = {
  getReports(page = 1) {
    return api.get(`/comments/reports/all?page=${page}`);
  },

  deleteComment(commentId) {
    return api.delete(`/comments/${commentId}/delete`);
  },

  deleteReport(reportId) {
    return api.delete(`/comments/reports/${reportId}/delete`);
  },
};
