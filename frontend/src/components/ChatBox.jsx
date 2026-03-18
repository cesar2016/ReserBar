import { useState, useContext, useRef, useEffect } from 'react';
import API_URL from '../config/api';
import { AuthContext } from '../context/AuthContext';
import './ChatBox.css';

const MODELS = [
  { value: 'llama-3.1-70b-versatile', label: 'Llama 3.1 70B' },
  { value: 'llama-3.1-8b-instant', label: 'Llama 3.1 8B' },
  { value: 'llama-3-70b-8192', label: 'Llama 3 70B' },
];

const HORARIOS = {
  lunes: { label: 'Lunes a Viernes', hours: generateHours(10, 23) },
  martes: { label: 'Lunes a Viernes', hours: generateHours(10, 23) },
  miercoles: { label: 'Lunes a Viernes', hours: generateHours(10, 23) },
  jueves: { label: 'Lunes a Viernes', hours: generateHours(10, 23) },
  viernes: { label: 'Lunes a Viernes', hours: generateHours(10, 23) },
  sabado: { label: 'Sábado', hours: ['22:00', '23:00'] },
  domingo: { label: 'Domingo', hours: generateHours(12, 15) },
};

const HORARIOS_TEXTOS = `🕐 Nuestros horarios:

• Lunes a Viernes: 10:00 a 00:00
• Sábado: 22:00 a 00:00
• Domingo: 12:00 a 16:00`;

function generateHours(start, end) {
  const hours = [];
  for (let h = start; h <= end; h++) {
    hours.push(`${h.toString().padStart(2, '0')}:00`);
  }
  return hours;
}

function getDayOfWeek(dateString) {
  const date = new Date(dateString);
  const days = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
  return days[date.getDay()];
}

function formatDateForDisplay(dateString) {
  const parts = dateString.split('-');
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

export default function ChatBox() {
  const { user } = useContext(AuthContext);
  const [isOpen, setIsOpen] = useState(false);
  
  const userName = user?.name ? user.name.split(' ')[0] : 'Cliente';
  
  const [messages, setMessages] = useState([]);
  const [showGreeting, setShowGreeting] = useState(false);
  const [model, setModel] = useState('llama-3.1-8b-instant');
  const [isTyping, setIsTyping] = useState(false);
  const [reservationData, setReservationData] = useState({
    date: '',
    dateDisplay: '',
    time: '',
    guest_count: ''
  });
  const [currentStep, setCurrentStep] = useState('inicio');
  const [showConfirmation, setShowConfirmation] = useState(false);
  const [availableHours, setAvailableHours] = useState([]);
  const messagesEndRef = useRef(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages, isTyping]);

  useEffect(() => {
    if (isOpen && messages.length === 0) {
      setShowGreeting(true);
      addMessage('assistant', `¡Hola ${userName}! 😊\n\nSoy el asistente de ReserBar. ¿En qué puedo ayudarte hoy?`);
    }
  }, [isOpen]);

  const addMessage = (role, content) => {
    setMessages(prev => [...prev, { role, content }]);
  };

  const showTypingIndicator = () => {
    setIsTyping(true);
  };

  const hideTypingIndicator = () => {
    setIsTyping(false);
  };

  const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

  const handleAsyncAction = async (userMessage, apiMessage, reservationDataToSend = {}) => {
    addMessage('user', userMessage);
    showTypingIndicator();
    
    await delay(800 + Math.random() * 700);
    
    try {
      const response = await fetch(`${API_URL}/api/chat`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          message: apiMessage,
          model,
          user_id: user?.id,
          reservation_data: reservationDataToSend
        }),
      });

      const data = await response.json();
      hideTypingIndicator();

      if (data.error) {
        addMessage('assistant', `Error: ${data.error}`);
      } else {
        addMessage('assistant', data.response);
        if (data.reservation_created) {
          resetReservation();
        }
      }
    } catch (error) {
      hideTypingIndicator();
      addMessage('assistant', `Error de conexión: ${error.message}`);
    }
  };

  const resetReservation = () => {
    setReservationData({ date: '', dateDisplay: '', time: '', guest_count: '' });
    setShowConfirmation(false);
    setCurrentStep('inicio');
    setAvailableHours([]);
  };

  const handleRestart = () => {
    setMessages([]);
    resetReservation();
    addMessage('assistant', `¡Hola ${userName}! 😊\n\nSoy el asistente de ReserBar. ¿En qué puedo ayudarte hoy?`);
  };

  const handleClose = () => {
    setMessages([]);
    resetReservation();
    setIsOpen(false);
  };

  const handleConfirm = async (confirm) => {
    if (!confirm) {
      addMessage('user', '❌ Modificar');
      addMessage('assistant', 'Entendido. Empecemos de nuevo.\n\n📅 ¿Para qué fecha?');
      setCurrentStep('fecha');
      setReservationData({ date: '', dateDisplay: '', time: '', guest_count: '' });
      return;
    }

    showTypingIndicator();
    addMessage('user', '✅ Confirmar reserva');

    await delay(1000 + Math.random() * 500);

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
          resetReservation();
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
    const dateDisplay = formatDateForDisplay(date);
    const dayOfWeek = getDayOfWeek(date);
    const dayInfo = HORARIOS[dayOfWeek] || HORARIOS.lunes;
    
    setAvailableHours(dayInfo.hours);
    
    addMessage('assistant', `📅 Fecha: ${dateDisplay}\n\n🕐 ¿A qué hora?\n(${dayInfo.label}: ${dayInfo.hours[0]} a ${dayInfo.hours[dayInfo.hours.length - 1]})`);
    
    setReservationData(prev => ({ 
      ...prev, 
      date: date,
      dateDisplay: dateDisplay,
      time: ''
    }));
    setCurrentStep('hora');
  };

  const handleTimeSelect = (time) => {
    addMessage('assistant', `🕐 Hora: ${time}\n\n👥 ¿Para cuántas personas?\n(Mínimo 1 - Máximo 8)`);
    
    setReservationData(prev => ({ ...prev, time }));
    setCurrentStep('personas');
  };

  const handlePeopleSelect = (count) => {
    const summary = `👥 Personas: ${count}

📋 Resumen de tu reserva:
📅 ${reservationData.dateDisplay}
🕐 ${reservationData.time}
👥 ${count} ${count === 1 ? 'persona' : 'personas'}

¿Confirmás?`;

    addMessage('assistant', summary);
    
    setReservationData(prev => ({ ...prev, guest_count: count }));
    setShowConfirmation(true);
  };

  const handleQuickAction = async (action) => {
    if (action === 'reservar') {
      handleStartReservation();
    } else if (action === 'horarios') {
      addMessage('user', '¿Cuáles son los horarios?');
      showTypingIndicator();
      await delay(800);
      hideTypingIndicator();
      addMessage('assistant', HORARIOS_TEXTOS);
    } else if (action === 'ubicacion') {
      addMessage('user', '¿Dónde están ubicados?');
      showTypingIndicator();
      await delay(800);
      hideTypingIndicator();
      addMessage('assistant', '📍 Estamos en:\n\nAv. Principal 1234, Ciudad\n\n¡Te esperamos! 🍽️');
    } else if (action === 'disponibilidad') {
      addMessage('user', '¿Hay disponibilidad para hoy?');
      showTypingIndicator();
      await delay(800);
      hideTypingIndicator();
      addMessage('assistant', 'Para consultar disponibilidad necesito saber:\n\n📅 ¿Para qué fecha?\n🕐 ¿A qué hora?\n👥 ¿Cuántas personas?\n\n¿Querés hacer una reserva? 😊');
    }
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
          <button className="restart-btn" onClick={handleRestart} title="Reiniciar chat">
            🔄
          </button>
          <select value={model} onChange={(e) => setModel(e.target.value)}>
            {MODELS.map(m => (
              <option key={m.value} value={m.value}>{m.label}</option>
            ))}
          </select>
          <button className="close-btn" onClick={handleClose}>×</button>
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

      {currentStep === 'inicio' && !showConfirmation && messages.length === 0 && (
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

      {currentStep === 'inicio' && !showConfirmation && messages.length > 0 && (
        <div className="quick-actions-whatsapp">
          <button className="quick-btn primary" onClick={() => handleQuickAction('reservar')}>
            📅 Nueva Reserva
          </button>
          <button className="quick-btn" onClick={() => handleQuickAction('horarios')}>
            🕐 Horarios
          </button>
          <button className="quick-btn restart" onClick={handleRestart}>
            🔄 Nueva Charla
          </button>
        </div>
      )}

      {currentStep === 'fecha' && !showConfirmation && (
        <div className="chat-input-area">
          <input
            type="date"
            className="date-input"
            min={new Date().toISOString().split('T')[0]}
            onChange={(e) => e.target.value && handleDateSelect(e.target.value)}
          />
        </div>
      )}

      {currentStep === 'hora' && !showConfirmation && (
        <div className="chat-input-area">
          <div className="hours-info">
            {availableHours.length > 0 ? `Horario: ${availableHours[0]} - ${availableHours[availableHours.length - 1]}` : 'Seleccioná una hora'}
          </div>
          <div className="options-grid hours-grid">
            {availableHours.map(h => (
              <button key={h} className="option-btn hour-btn" onClick={() => handleTimeSelect(h)}>
                {h}
              </button>
            ))}
          </div>
        </div>
      )}

      {currentStep === 'personas' && !showConfirmation && (
        <div className="chat-input-area">
          <div className="hours-info">Seleccioná cantidad de personas (1-8)</div>
          <div className="options-grid people-grid">
            {[1, 2, 3, 4, 5, 6, 7, 8].map(n => (
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
