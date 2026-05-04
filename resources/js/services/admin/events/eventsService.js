// src/services/admin/events/eventsService.js
import api from '../../ApiClient';

class EventService {
  async getAllEvents(page = 1) {
    try {
      const response = await api.get(`/events`, {
        params: { page }
      });
      return response.data;
    } catch (error) {
      console.error('Error fetching events:', error);
      throw error;
    }
  }

  async deleteEvent(id) {
    try {
      const response = await api.delete(`/events/${id}/delete`);
      return response.data;
    } catch (error) {
      console.error('Error deleting event:', error);
      throw error;
    }
  }
}

export default new EventService();
