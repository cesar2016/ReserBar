import { useState, useContext, useRef, useEffect } from 'react';
import API_URL from '../config/api';
import { AuthContext } from '../context/AuthContext';
import './ChatBox.css';

const MODELS = [
  { value: 'llama-3.1-70b-versatile', label: 'Llama 3.1 70B' },
  { value: 'llama-3.1-8b-instant', label: 'Llama 3.1 8B' },
  { value: 'llama-3-70b-8192', label: 'Llama 3 70B' },
];

export default function ChatBox() {
  const { user } = useContext(AuthContext);
  const [isOpen, setIsOpen] = useState(false);
  
  const userName = user?.name ? user.name.split(' ')[0] : 'Cliente';
  
  const [messages, setMessages] = useState([
    { role: 'assistant', content: `¡Hola ${userName}! 👋 Soy el asistente de ReserBar. ¿En qué puedo ayudarte hoy?` }
  ]);
  const [input, setInput] = useState('');
  const [model, setModel] = useState('llama-3.1-8b-instant');
  const [loading, setLoading] = useState(false);
  const [systemPrompt, setSystemPrompt] = useState('');
  const [reservationData, setReservationData] = useState({});
  const messagesEndRef = useRef(null);

  useEffect(() => {
    const basePrompt = `Eres el asistente de "ReserBar", restaurante.

REGLAS:
- Solo habla del restaurante
- Responde en español
- Sé breve y directo

HORARIOS DE RESERVA:
- Lunes a Viernes: 10:00 a 24:00
- Sábado: 22:00 a 02:00 (del domingo)
- Domingo: 12:00 a 16:00

El sistema maneja la extracción de datos automáticamente.
Tú solo necesitas responder según el flujo.`;

    setSystemPrompt(basePrompt);
  }, [user]);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  const sendToAI = async (message, currentReservationData = {}) => {
    setLoading(true);

    try {
      const response = await fetch(`${API_URL}/api/chat`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          message, 
          model,
          user_id: user?.id,
          system_prompt: systemPrompt,
          reservation_data: currentReservationData
        }),
      });

      const data = await response.json();

      if (data.error) {
        return { error: data.error };
      }
      return { 
        response: data.response, 
        reservation_data: data.reservation_data || {},
        reservation_created: data.reservation_created
      };
    } catch (error) {
      return { error: error.message };
    }
  };

  const handleUserMessage = async (message) => {
    setMessages(prev => [...prev, { role: 'user', content: message }]);
    setInput('');
    setLoading(true);

    const result = await sendToAI(message, reservationData);

    setLoading(false);

    if (result.error) {
      setMessages(prev => [...prev, { role: 'assistant', content: `Error: ${result.error}` }]);
    } else {
      setMessages(prev => [...prev, { role: 'assistant', content: result.response }]);
      
      if (result.reservation_data) {
        if (result.reservation_created) {
          setReservationData({});
        } else {
          setReservationData(result.reservation_data);
        }
      }
    }
  };

  const handleQuickAction = async (action) => {
    const actionMessages = {
      reservar: `${userName} quiere hacer una reserva`,
      disponibilidad: '¿Hay disponibilidad para hoy?',
      horarios: '¿Cuáles son los horarios del restaurante?',
      ubicacion: '¿Dónde está ubicado el restaurante?'
    };
    handleUserMessage(actionMessages[action]);
  };

  if (!isOpen) {
    return (
      <div className="chat-fab" onClick={() => setIsOpen(true)}>
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
        <span className="notification-dot"></span>
      </div>
    );
  }

  return (
    <div className="chat-container-whatsapp">
      <div className="chat-header-whatsapp">
        <div className="header-left">
          <div className="avatar">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
          </div>
          <div className="header-info">
            <h3>ReserBar</h3>
            <span>en línea</span>
          </div>
        </div>
        <div className="header-actions">
          <select value={model} onChange={(e) => setModel(e.target.value)}>
            {MODELS.map(m => (
              <option key={m.value} value={m.value}>{m.label}</option>
            ))}
          </select>
          <button className="close-btn" onClick={() => setIsOpen(false)}>×</button>
        </div>
      </div>

      <div className="chat-messages-whatsapp">
        <div className="chat-start-info">
          🤖 Asistente Virtual de ReserBar
        </div>
        {messages.map((msg, i) => (
          <div key={i} className={`message-wrapper ${msg.role === 'user' ? 'user-wrapper' : 'ai-wrapper'}`}>
            {msg.role === 'assistant' && (
              <div className="message-avatar">
                <svg viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
              </div>
            )}
            <div className={`message-bubble ${msg.role === 'user' ? 'user-bubble' : 'ai-bubble'}`}>
              <div className="message-content">{msg.content}</div>
            </div>
          </div>
        ))}
        {loading && (
          <div className="message-wrapper ai-wrapper">
            <div className="message-avatar">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
            </div>
            <div className="message-bubble ai-bubble">
              <div className="typing-indicator">
                <span></span><span></span><span></span>
              </div>
            </div>
          </div>
        )}
        <div ref={messagesEndRef} />
      </div>

      <div className="quick-actions-whatsapp">
        <button className="quick-btn" onClick={() => handleQuickAction('reservar')}>
          📅 Hacer Reserva
        </button>
        <button className="quick-btn" onClick={() => handleQuickAction('disponibilidad')}>
          ✅ Disponibilidad
        </button>
        <button className="quick-btn" onClick={() => handleQuickAction('horarios')}>
          🕐 Horarios
        </button>
        <button className="quick-btn" onClick={() => handleQuickAction('ubicacion')}>
          📍 Ubicación
        </button>
      </div>

      <form className="chat-input-whatsapp" onSubmit={(e) => { e.preventDefault(); if(input.trim()) handleUserMessage(input); }}>
        <input
          type="text"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Escribe un mensaje..."
          disabled={loading}
        />
        <button type="submit" disabled={loading || !input.trim()}>
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
          </svg>
        </button>
      </form>
    </div>
  );
}
