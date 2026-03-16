// src/services/admin/events/eventsService.js
import axios from 'axios';

const API_BASE_URL = '/v1';

class EventService {
  async getAllEvents(page = 1) {
    try {
      const response = await axios.get(`${API_BASE_URL}/events`, {
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
      const response = await axios.delete(`${API_BASE_URL}/events/${id}/delete`);
      return response.data;
    } catch (error) {
      console.error('Error deleting event:', error);
      throw error;
    }
  }
}

export default new EventService();
