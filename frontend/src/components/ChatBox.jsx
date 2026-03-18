import { useState, useContext, useRef, useEffect } from 'react';
import API_URL from '../config/api';
import { AuthContext } from '../context/AuthContext';
import './ChatBox.css';

const MODELS = [
  { value: 'llama-3.1-70b-versatile', label: 'Llama 3.1 70B' },
  { value: 'llama-3.1-8b-instant', label: 'Llama 3.1 8B' },
  { value: 'llama-3-70b-8192', label: 'Llama 3 70B' },
];

const HOURS = [];
for (let h = 10; h <= 24; h++) {
  const hour = h % 24;
  HOURS.push(`${hour.toString().padStart(2, '0')}:00`);
}

export default function ChatBox() {
  const { user } = useContext(AuthContext);
  const [isOpen, setIsOpen] = useState(false);
  
  const userName = user?.name ? user.name.split(' ')[0] : 'Cliente';
  const inputRef = useRef(null);
  
  const [messages, setMessages] = useState([
    { role: 'assistant', content: `¡Hola ${userName}! 😊\n\nSoy el asistente de ReserBar. Estaré encantado de ayudarte.` }
  ]);
  const [model, setModel] = useState('llama-3.1-8b-instant');
  const [loading, setLoading] = useState(false);
  const [isTyping, setIsTyping] = useState(false);
  const [typingMessage, setTypingMessage] = useState('');
  const [reservationData, setReservationData] = useState({
    date: '',
    time: '',
    guest_count: ''
  });
  const [currentStep, setCurrentStep] = useState('inicio');
  const [showConfirmation, setShowConfirmation] = useState(false);
  const messagesEndRef = useRef(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages, isTyping]);

  const addMessage = (role, content) => {
    setMessages(prev => [...prev, { role, content }]);
  };

  const showTypingIndicator = (message = 'Escribiendo...') => {
    setIsTyping(true);
    setTypingMessage(message);
  };

  const hideTypingIndicator = () => {
    setIsTyping(false);
    setTypingMessage('');
  };

  const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

  const handleAsyncAction = async (userMessage, apiCall, extraData = {}) => {
    addMessage('user', userMessage);
    showTypingIndicator('Pensando...');
    
    await delay(800 + Math.random() * 700);
    
    try {
      const response = await fetch(`${API_URL}/api/chat`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          message: apiCall.message,
          model,
          user_id: user?.id,
          reservation_data: apiCall.reservation_data
        }),
      });

      const data = await response.json();
      hideTypingIndicator();

      if (data.error) {
        addMessage('assistant', `Error: ${data.error}`);
      } else {
        addMessage('assistant', data.response);
        if (data.reservation_created) {
          setReservationData({ date: '', time: '', guest_count: '' });
          setShowConfirmation(false);
          setCurrentStep('inicio');
        }
      }
    } catch (error) {
      hideTypingIndicator();
      addMessage('assistant', `Error de conexión: ${error.message}`);
    }
  };

  const handleConfirm = async (confirm) => {
    if (!confirm) {
      setReservationData({ date: '', time: '', guest_count: '' });
      setShowConfirmation(false);
      setCurrentStep('fecha');
      addMessage('user', 'Modificar');
      addMessage('assistant', 'Entendido. Empecemos de nuevo.\n\n📅 ¿Para qué fecha?');
      return;
    }

    showTypingIndicator('Confirmando tu reserva...');
    
    await delay(1000 + Math.random() * 500);
    
    addMessage('user', '✅ Confirmar');

    try {
      const response = await fetch(`${API_URL}/api/chat`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          message: 'SI',
          model,
          user_id: user?.id,
          reservation_data: {
            date: reservationData.date,
            time: reservationData.time,
            guest_count: parseInt(reservationData.guest_count),
            ready: true
          }
        }),
      });

      const data = await response.json();
      hideTypingIndicator();

      if (data.error) {
        addMessage('assistant', `Error: ${data.error}`);
      } else {
        addMessage('assistant', data.response);
        if (data.reservation_created) {
          setReservationData({ date: '', time: '', guest_count: '' });
          setShowConfirmation(false);
          setCurrentStep('inicio');
        }
      }
    } catch (error) {
      hideTypingIndicator();
      addMessage('assistant', `Error de conexión: ${error.message}`);
    }
  };

  const handleStartReservation = () => {
    addMessage('user', '📅 Quiero hacer una reserva');
    addMessage('assistant', '¡Genial! 😊\n\n📅 ¿Para qué fecha?');
    setCurrentStep('fecha');
  };

  const handleDateSelect = (date) => {
    const formattedDate = date.split('-').reverse().join('/');
    setReservationData(prev => ({ ...prev, date: formattedDate }));
    setCurrentStep('hora');
    addMessage('assistant', `📅 Fecha: ${formattedDate}\n\n🕐 ¿A qué hora?`);
  };

  const handleTimeSelect = (time) => {
    setReservationData(prev => ({ ...prev, time }));
    setCurrentStep('personas');
    addMessage('assistant', `🕐 Hora: ${time}\n\n👥 ¿Para cuántas personas?`);
  };

  const handlePeopleSelect = (count) => {
    setReservationData(prev => ({ ...prev, guest_count: count }));
    setShowConfirmation(true);
    addMessage('assistant', `👥 Personas: ${count}\n\n📋 Resumen:\n📅 ${reservationData.date}\n🕐 ${reservationData.time}\n👥 ${count} personas\n\n¿Confirmás?`);
  };

  const handleQuickAction = async (action) => {
    if (action === 'reservar') {
      handleStartReservation();
    } else if (action === 'horarios') {
      await handleAsyncAction(
        '¿Cuáles son los horarios?',
        { message: 'horarios', reservation_data: {} }
      );
    } else if (action === 'ubicacion') {
      await handleAsyncAction(
        '¿Dónde están ubicados?',
        { message: 'ubicacion', reservation_data: {} }
      );
    } else if (action === 'disponibilidad') {
      await handleAsyncAction(
        '¿Hay disponibilidad para hoy?',
        { message: 'disponibilidad', reservation_data: {} }
      );
    }
  };

  if (!isOpen) {
    return (
      <div className="chat-fab" onClick={() => setIsOpen(true)}>
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
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
        
        {isTyping && (
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

      {currentStep === 'inicio' && !showConfirmation && (
        <div className="quick-actions-whatsapp">
          <button className="quick-btn primary" onClick={() => handleQuickAction('reservar')}>
            📅 Hacer Reserva
          </button>
          <button className="quick-btn" onClick={() => handleQuickAction('horarios')}>
            🕐 Horarios
          </button>
          <button className="quick-btn" onClick={() => handleQuickAction('ubicacion')}>
            📍 Ubicación
          </button>
        </div>
      )}

      {currentStep === 'fecha' && !showConfirmation && (
        <div className="chat-input-area">
          <input
            type="date"
            className="date-input"
            min={new Date().toISOString().split('T')[0]}
            onChange={(e) => handleDateSelect(e.target.value)}
          />
        </div>
      )}

      {currentStep === 'hora' && !showConfirmation && (
        <div className="chat-input-area">
          <div className="options-grid">
            {HOURS.map(h => (
              <button key={h} className="option-btn" onClick={() => handleTimeSelect(h)}>
                {h}
              </button>
            ))}
          </div>
        </div>
      )}

      {currentStep === 'personas' && !showConfirmation && (
        <div className="chat-input-area">
          <div className="options-grid people-grid">
            {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map(n => (
              <button 
                key={n} 
                className="option-btn people-btn"
                onClick={() => handlePeopleSelect(n)}
              >
                {n}
              </button>
            ))}
          </div>
        </div>
      )}

      {showConfirmation && (
        <div className="chat-input-area confirmation">
          <p>¿Confirmás la reserva?</p>
          <div className="confirm-buttons">
            <button className="confirm-btn yes" onClick={() => handleConfirm(true)}>
              ✅ Sí, confirmar
            </button>
            <button className="confirm-btn no" onClick={() => handleConfirm(false)}>
              ❌ Modificar
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
