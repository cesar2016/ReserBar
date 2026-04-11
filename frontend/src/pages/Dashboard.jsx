import { useState, useEffect, useContext } from 'react';
import { AuthContext } from '../context/AuthContext';
import axios from 'axios';
import API_URL from '../config/api';
import { toast } from 'sonner';
import Spinner from '../components/Spinner';
import ChatBox from '../components/ChatBox';
import { Calendar, Users, Plus, LogOut, Clock, MapPin, ChefHat, Trash2, Edit, X } from 'lucide-react';

const Dashboard = () => {
    const { user, logout } = useContext(AuthContext);
    const isAdmin = user?.id === 1;
    const [activeTab, setActiveTab] = useState('reservations');
    const [reservations, setReservations] = useState([]);
    const [tables, setTables] = useState([]);
    const [menu, setMenu] = useState([]);
    const [loading, setLoading] = useState(true);

    // Reservation Form State
    const [showReservationForm, setShowReservationForm] = useState(false);
    const [editingReservationId, setEditingReservationId] = useState(null);
    const [suggestion, setSuggestion] = useState(null); // { date, time, label, guest_count }
    const [errorAlert, setErrorAlert] = useState(null); // { message, alternatives }
    const [resFormData, setResFormData] = useState({ 
        date: new Date().toISOString().split('T')[0], 
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false }), 
        guest_count: 2 
    });

    // Mesas Tab State
    const [tableViewDate, setTableViewDate] = useState(new Date().toISOString().split('T')[0]);
    const [currentTime, setCurrentTime] = useState(new Date());

    // Admin Availability State
    const [queryDate, setQueryDate] = useState(new Date().toISOString().split('T')[0]);
    const [queryTime, setQueryTime] = useState(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false }));
    const [availableTablesForQuery, setAvailableTablesForQuery] = useState([]);

    // Modal State
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [reservationToDelete, setReservationToDelete] = useState(null);
    const [showTableReservationsModal, setShowTableReservationsModal] = useState(false);
    const [selectedTableReservations, setSelectedTableReservations] = useState([]);
    const [selectedTableName, setSelectedTableName] = useState('');

    useEffect(() => {
        const timer = setInterval(() => setCurrentTime(new Date()), 1000); // Update every second
        return () => clearInterval(timer);
    }, []);

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        setLoading(true);
        try {
            const token = localStorage.getItem('token');
            const config = { headers: { Authorization: `Bearer ${token}` } };
            
            const [reservationsRes, tablesRes, menuRes] = await Promise.all([
                axios.get(`${API_URL}/api/reservations`, config),
                axios.get(`${API_URL}/api/tables`, config),
                axios.get(`${API_URL}/api/menu`)
            ]);

            setReservations(reservationsRes.data);
            setTables(tablesRes.data);
            setMenu(menuRes.data.data || []);
        } catch (error) {
            console.error(error);
            toast.error('Error al cargar datos');
        } finally {
            setLoading(false);
        }
    };

    const getTableName = (id) => {
        const table = tables.find(t => t.id === id);
        return table ? `${table.location}${table.number}` : `Mesa ${id}`;
    };

    const formatDate = (dateStr) => {
        const [year, month, day] = dateStr.split('-');
        return `${day}-${month}-${year}`;
    };

    const getTimeRemaining = (date, time) => {
        const reservationDate = new Date(`${date}T${time}`);
        const endTime = new Date(reservationDate.getTime() + 2 * 60 * 60 * 1000); // +2 hours
        const diff = endTime - currentTime;

        if (diff <= 0) return { text: "Libre", minutesLeft: 0, isEnding: false };

        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        const text = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        const totalMinutes = hours * 60 + minutes;
        
        return { text, minutesLeft: totalMinutes, isEnding: totalMinutes < 15 };
    };

    const isReservationActive = (date, time) => {
        const reservationDate = new Date(`${date}T${time}`);
        const endTime = new Date(reservationDate.getTime() + 2 * 60 * 60 * 1000);
        return currentTime >= reservationDate && currentTime < endTime;
    };

    const isTableOccupiedAt = (tableId, date, time) => {
        const checkTime = new Date(`${date}T${time}`);
        return reservations.some(res => {
            if (res.date !== date) return false;
            if (!res.table_ids.includes(tableId)) return false;
            const resStart = new Date(`${res.date}T${res.time}`);
            const resEnd = new Date(resStart.getTime() + 2 * 60 * 60 * 1000);
            return checkTime >= resStart && checkTime < resEnd;
        });
    };

    const handleCreateReservation = async (e) => {
        e.preventDefault();
        
        const [year, month, day] = resFormData.date.split('-').map(Number);
        const [hour, minute] = resFormData.time.split(':').map(Number);
        const selectedDateTime = new Date(year, month - 1, day, hour, minute);
        
        const now = new Date();
        const minTime = new Date(now.getTime() + 15 * 60 * 1000); // Current time + 15 minutes
        
        if (selectedDateTime < minTime) {
            toast.error('Las reservas deben hacerse con al menos 15 minutos de anticipación');
            return;
        }

        try {
            const token = localStorage.getItem('token');
            
            const payload = {
                date: resFormData.date,
                time: resFormData.time,
                guest_count: parseInt(resFormData.guest_count)
            };

            if (editingReservationId) {
                await axios.put(`${API_URL}/api/reservations/${editingReservationId}`, payload, {
                    headers: { Authorization: `Bearer ${token}` }
                });
                toast.success('Reserva actualizada correctamente');
            } else {
                await axios.post(`${API_URL}/api/reservations`, payload, {
                    headers: { Authorization: `Bearer ${token}` }
                });
                toast.success('Reserva creada correctamente');
            }
            
            resetForm();
            fetchData();
        } catch (error) {
            console.error(error);
            const message = error.response?.data?.message || 'Error al guardar reserva';
            
            if (error.response?.data?.alternatives?.next_slot) {
                setErrorAlert({
                    message,
                    alternatives: error.response.data.alternatives
                });
            } else {
                toast.error(message);
            }
        }
    };

    const handleCloseErrorAlert = () => {
        if (errorAlert?.alternatives?.next_slot) {
            setSuggestion({
                ...errorAlert.alternatives.next_slot,
                guest_count: resFormData.guest_count
            });
        }
        setErrorAlert(null);
    };

    const handleConfirmSuggestion = async () => {
        if (!suggestion) return;
        
        try {
            const token = localStorage.getItem('token');
            const payload = {
                date: suggestion.date,
                time: suggestion.time,
                guest_count: parseInt(suggestion.guest_count)
            };

            await axios.post(`${API_URL}/api/reservations`, payload, {
                headers: { Authorization: `Bearer ${token}` }
            });
            
            toast.success('Reserva confirmada exitosamente');
            setSuggestion(null);
            resetForm();
            fetchData();
        } catch (error) {
            console.error(error);
            toast.error('Error al confirmar sugerencia');
        }
    };

    const resetForm = () => {
        setShowReservationForm(false);
        setEditingReservationId(null);
        setResFormData({ 
            date: new Date().toISOString().split('T')[0], 
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false }), 
            guest_count: 2 
        });
    };

    const handleEditClick = (res) => {
        setEditingReservationId(res.id);
        setResFormData({
            date: res.date,
            time: res.time.substring(0, 5),
            guest_count: res.guest_count
        });
        setShowReservationForm(true);
        window.scrollTo(0, 0);
    };

    const handleDeleteReservation = (res) => {
        setReservationToDelete(res);
        setShowDeleteModal(true);
    };

    const confirmDelete = async () => {
        if (!reservationToDelete) return;
        
        try {
            const token = localStorage.getItem('token');
            await axios.delete(`${API_URL}/api/reservations/${reservationToDelete.id}`, {
                headers: { Authorization: `Bearer ${token}` }
            });
            toast.success('Reserva eliminada');
            setShowDeleteModal(false);
            setReservationToDelete(null);
            fetchData();
        } catch (error) {
            console.error(error);
            toast.error('Error al eliminar reserva');
            setShowDeleteModal(false);
        }
    };

    return (
        <>
        <div style={{ minHeight: '100vh', background: '#f3f4f6' }}>
            {/* Header */}
            <header style={{ background: 'white', borderBottom: '1px solid #e5e7eb', padding: '1rem 2rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center', position: 'sticky', top: 0, zIndex: 10 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                    <div style={{ background: 'var(--primary-gradient)', padding: '0.5rem', borderRadius: '8px', color: 'white' }}>
                        <ChefHat size={24} />
                    </div>
                    <h1 style={{ fontSize: '1.5rem', fontWeight: '800', margin: 0 }}>
                        Reser<span className="gradient-text">Bar</span>
                    </h1>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                    <span style={{ fontWeight: '600', color: '#374151' }}>Hola, {user?.name}</span>
                    <button 
                        onClick={logout} 
                        className="btn btn-outline" 
                        style={{ padding: '0.5rem 1rem', borderRadius: '12px', fontSize: '0.9rem' }}
                    >
                        <LogOut size={18} /> Salir
                    </button>
                </div>
            </header>

            <main style={{ padding: '2rem', maxWidth: '1200px', margin: '0 auto' }}>
                {/* Tabs */}
                <div style={{ display: 'flex', gap: '1rem', marginBottom: '2rem', overflowX: 'auto', paddingBottom: '0.5rem', flexWrap: 'wrap' }}>
                    <button 
                        onClick={() => setActiveTab('reservations')}
                        className={`btn ${activeTab === 'reservations' ? 'btn-primary' : 'btn-outline'}`}
                        style={{ borderRadius: '12px', whiteSpace: 'nowrap' }}
                    >
                        <Calendar size={18} /> Reservaciones
                    </button>
                    <button 
                        onClick={() => setActiveTab('tables')}
                        className={`btn ${activeTab === 'tables' ? 'btn-primary' : 'btn-outline'}`}
                        style={{ borderRadius: '12px', whiteSpace: 'nowrap' }}
                    >
                        <MapPin size={18} /> Mesas
                    </button>
                    <button 
                        onClick={() => setActiveTab('menu')}
                        className={`btn ${activeTab === 'menu' ? 'btn-primary' : 'btn-outline'}`}
                        style={{ borderRadius: '12px', whiteSpace: 'nowrap' }}
                    >
                        <ChefHat size={18} /> Carta
                    </button>
                    {isAdmin && (
                        <button 
                            onClick={() => setActiveTab('admin_availability')}
                            className={`btn ${activeTab === 'admin_availability' ? 'btn-primary' : 'btn-outline'}`}
                            style={{ borderRadius: '12px', whiteSpace: 'nowrap' }}
                        >
                            <Calendar size={18} /> Disponibilidad Admin
                        </button>
                    )}
                </div>

                {loading ? (
                    <div style={{ display: 'flex', justifyContent: 'center', padding: '4rem' }}>
                        <Spinner size={40} />
                    </div>
                ) : (
                    <div className="glass-panel fade-in" style={{ padding: '2rem', borderRadius: 'var(--radius-2xl)' }}>
                        
                        {/* Tables Tab */}
                        {activeTab === 'tables' && (
                            <div>
                                <div style={{ marginBottom: '1.5rem' }}>
                                    <h3 style={{ fontSize: '1.25rem', fontWeight: '700', color: '#111827', marginBottom: '1rem' }}>Estado de Mesas</h3>
                                    
                                    <div style={{ marginBottom: '1rem' }}>
                                        <label style={{ fontWeight: '600', marginRight: '1rem' }}>Fecha:</label>
                                        <input 
                                            type="date" 
                                            value={tableViewDate}
                                            onChange={e => setTableViewDate(e.target.value)}
                                            className="input"
                                            style={{ width: 'auto', display: 'inline-block' }}
                                        />
                                    </div>
                                </div>

                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(250px, 1fr))', gap: '1.5rem' }}>
                                    {tables.map(table => {
                                        const occupied = isTableOccupiedAt(table.id, tableViewDate, currentTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false }));
                                        
                                        // Find the active reservation for this table on this date
                                        let activeReservation = null;
                                        if (occupied) {
                                            activeReservation = reservations.find(res => 
                                                res.date === tableViewDate && 
                                                res.table_ids.includes(table.id) && 
                                                isReservationActive(res.date, res.time)
                                            );
                                        }

                                        return (
                                            <div 
                                                key={table.id} 
                                                style={{ 
                                                    border: occupied ? '2px solid #ef4444' : '1px solid #e5e7eb', 
                                                    padding: '1.5rem', 
                                                    borderRadius: '16px',
                                                    background: occupied ? '#fef2f2' : 'white',
                                                    transition: 'transform 0.2s',
                                                    boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.05)'
                                                }}
                                            >
                                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1rem' }}>
                                                    <div style={{ 
                                                        background: occupied ? '#fee2e2' : '#e0e7ff', 
                                                        color: occupied ? '#ef4444' : '#4f46e5', 
                                                        padding: '0.5rem', 
                                                        borderRadius: '10px' 
                                                    }}>
                                                        <MapPin size={20} />
                                                    </div>
                                                    {occupied && activeReservation ? (
                                                        (() => {
                                                            const timer = getTimeRemaining(activeReservation.date, activeReservation.time);
                                                            const isEnding = timer.isEnding;
                                                            return (
                                                                <div style={{ 
                                                                    fontSize: '1.2rem', 
                                                                    color: 'white',
                                                                    background: isEnding ? '#ef4444' : '#10b981', 
                                                                    padding: '0.5rem 1rem', 
                                                                    borderRadius: '8px',
                                                                    fontWeight: 'bold',
                                                                    display: 'flex',
                                                                    alignItems: 'center',
                                                                    gap: '8px',
                                                                    boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
                                                                }}>
                                                                    <Clock size={20} color="white" />
                                                                    {timer.text}
                                                                </div>
                                                            );
                                                        })()
                                                    ) : (
                                                        <span style={{ 
                                                            fontSize: '0.8rem', 
                                                            color: occupied ? '#ef4444' : '#6b7280', 
                                                            background: occupied ? '#fee2e2' : '#f3f4f6', 
                                                            padding: '0.25rem 0.5rem', 
                                                            borderRadius: '6px',
                                                            fontWeight: 'bold'
                                                        }}>
                                                            {occupied ? 'OCUPADA' : 'LIBRE'}
                                                        </span>
                                                    )}
                                                </div>
                                                    <h4 style={{ fontSize: '1.1rem', fontWeight: 'bold', marginBottom: '0.5rem', cursor: 'pointer' }} 
                                                        onClick={() => {
                                                            const resList = reservations.filter(r => r.table_ids.includes(table.id));
                                                            setSelectedTableReservations(resList);
                                                            setSelectedTableName(`Mesa ${table.location}${table.number}`);
                                                            setShowTableReservationsModal(true);
                                                        }}
                                                    >
                                                        Mesa {table.location}{table.number}
                                                    </h4>
                                                    <p style={{ color: '#6b7280', fontSize: '0.9rem' }}>Capacidad: <strong>{table.capacity}</strong> personas</p>
                                                    <button 
                                                        onClick={() => {
                                                            const resList = reservations.filter(r => r.table_ids.includes(table.id));
                                                            setSelectedTableReservations(resList);
                                                            setSelectedTableName(`Mesa ${table.location}${table.number}`);
                                                            setShowTableReservationsModal(true);
                                                        }}
                                                        style={{ 
                                                            color: '#4f46e5', 
                                                            fontSize: '0.8rem', 
                                                            marginTop: '0.5rem',
                                                            background: '#e0e7ff',
                                                            border: 'none',
                                                            padding: '0.25rem 0.5rem',
                                                            borderRadius: '6px',
                                                            cursor: 'pointer',
                                                            fontWeight: 'bold'
                                                        }}
                                                    >
                                                        Total Reservas: <strong>{reservations.filter(r => r.table_ids.includes(table.id)).length}</strong>
                                                    </button>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Menu Tab */}
                        {activeTab === 'menu' && (
                            <div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem' }}>
                                    <h3 style={{ fontSize: '1.25rem', fontWeight: '700', color: '#111827' }}>Nuestra Carta</h3>
                                </div>

                                <div style={{ display: 'grid', gap: '2rem' }}>
                                    {menu.map(category => (
                                        <div key={category.id} style={{
                                            background: 'white',
                                            borderRadius: '16px',
                                            padding: '1.5rem',
                                            border: '1px solid #e5e7eb'
                                        }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '1rem' }}>
                                                <span style={{ fontSize: '1.5rem' }}>{category.icon}</span>
                                                <h4 style={{ fontSize: '1.1rem', fontWeight: '700', margin: 0 }}>{category.name}</h4>
                                            </div>
                                            
                                            <div style={{ display: 'grid', gap: '0.75rem' }}>
                                                {category.items.map(item => (
                                                    <div key={item.id} style={{
                                                        display: 'flex',
                                                        justifyContent: 'space-between',
                                                        alignItems: 'flex-start',
                                                        padding: '0.75rem',
                                                        background: '#f9fafb',
                                                        borderRadius: '8px'
                                                    }}>
                                                        <div style={{ flex: 1 }}>
                                                                <span style={{ fontWeight: '600', color: '#111827' }}>{item.name}</span>
                                                                {item.description && (
                                                                    <p style={{ margin: '0.25rem 0 0 0', fontSize: '0.85rem', color: '#6b7280' }}>
                                                                        {item.description}
                                                                    </p>
                                                                )}
                                                        </div>
                                                        <span style={{
                                                            fontWeight: '700',
                                                            color: '#059669',
                                                            background: '#ecfdf5',
                                                            padding: '0.25rem 0.75rem',
                                                            borderRadius: '6px',
                                                            fontSize: '0.9rem',
                                                            whiteSpace: 'nowrap'
                                                        }}>
                                                            {item.formatted_price}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                    {menu.length === 0 && (
                                        <p style={{ textAlign: 'center', color: '#9ca3af', padding: '3rem', background: '#f9fafb', borderRadius: '16px' }}>
                                            No hay items en el menú.
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Admin Availability Tab */}
                        {activeTab === 'admin_availability' && isAdmin && (
                            <div>
                                <h3 style={{ fontSize: '1.25rem', fontWeight: '700', color: '#111827', marginBottom: '1.5rem' }}>Consulta de Disponibilidad</h3>
                                
                                <div style={{ 
                                    background: '#f9fafb', 
                                    padding: '1.5rem', 
                                    borderRadius: '16px', 
                                    display: 'flex', 
                                    gap: '1rem', 
                                    flexWrap: 'wrap', 
                                    alignItems: 'flex-end',
                                    marginBottom: '2rem',
                                    border: '1px solid #e5e7eb'
                                }}>
                                    <div style={{ flex: '1', minWidth: '200px' }}>
                                        <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.9rem', fontWeight: '600' }}>Fecha a consultar</label>
                                        <input 
                                            type="date" 
                                            value={queryDate}
                                            onChange={e => setQueryDate(e.target.value)}
                                            className="input"
                                        />
                                    </div>
                                    <div style={{ flex: '1', minWidth: '150px' }}>
                                        <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.9rem', fontWeight: '600' }}>Hora a consultar</label>
                                        <input 
                                            type="time" 
                                            value={queryTime}
                                            onChange={e => setQueryTime(e.target.value)}
                                            className="input"
                                        />
                                    </div>
                                    <div style={{ paddingBottom: '5px' }}>
                                        <p style={{ margin: 0, color: '#6b7280', fontSize: '0.85rem' }}>
                                            Consultando para el {formatDate(queryDate)} a las {queryTime}
                                        </p>
                                    </div>
                                </div>

                                <div style={{ marginBottom: '1.5rem' }}>
                                    <h4 style={{ fontSize: '1.1rem', fontWeight: '600', marginBottom: '1rem' }}>Mesas Libres en este horario:</h4>
                                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: '1rem' }}>
                                        {tables.filter(table => !isTableOccupiedAt(table.id, queryDate, queryTime)).length > 0 ? (
                                            tables.filter(table => !isTableOccupiedAt(table.id, queryDate, queryTime)).map(table => (
                                                <div key={table.id} style={{
                                                    background: 'white',
                                                    padding: '1rem',
                                                    borderRadius: '12px',
                                                    border: '1px solid #10b981',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: '1rem',
                                                    boxShadow: '0 4px 6px -1px rgba(16, 185, 129, 0.1)'
                                                }}>
                                                    <div style={{ background: '#ecfdf5', color: '#10b981', padding: '0.5rem', borderRadius: '8px' }}>
                                                        <MapPin size={20} />
                                                    </div>
                                                    <div>
                                                        <p style={{ fontWeight: '700', margin: 0 }}>Mesa {table.location}{table.number}</p>
                                                        <p style={{ margin: 0, fontSize: '0.85rem', color: '#6b7280' }}>Capacidad: {table.capacity}</p>
                                                    </div>
                                                </div>
                                            ))
                                        ) : (
                                            <p style={{ gridColumn: '1/-1', textAlign: 'center', padding: '2rem', color: '#ef4444', fontWeight: 'bold' }}>
                                                ❌ No hay mesas libres para este horario.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Reservations Tab */}
                        {activeTab === 'reservations' && (
                            <div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
                                    <h3 style={{ fontSize: '1.25rem', fontWeight: '700', color: '#111827' }}>Lista de Reservaciones</h3>
                                    <button 
                                        onClick={() => {
                                            resetForm();
                                            setShowReservationForm(!showReservationForm);
                                        }} 
                                        className="btn btn-primary"
                                        style={{ borderRadius: '12px' }}
                                    >
                                        <Plus size={18} /> {showReservationForm ? 'Cancelar' : 'Nueva Reserva'}
                                    </button>
                                </div>

                                {errorAlert && (
                                    <div className="fade-in" style={{
                                        backgroundColor: '#fef2f2',
                                        border: '1px solid #fee2e2',
                                        borderRadius: '16px',
                                        padding: '1.5rem',
                                        marginBottom: '1.5rem',
                                        position: 'relative',
                                        display: 'flex',
                                        gap: '1rem',
                                        alignItems: 'flex-start'
                                    }}>
                                        <div style={{ padding: '0.5rem', background: '#fee2e2', borderRadius: '50%', color: '#ef4444' }}>
                                            <X size={20} fontWeight="bold" />
                                        </div>
                                        <div style={{ flex: 1 }}>
                                            <p style={{ 
                                                color: '#b91c1c', 
                                                margin: 0, 
                                                fontWeight: '600', 
                                                lineHeight: '1.6',
                                                whiteSpace: 'pre-line' 
                                            }}>
                                                {errorAlert.message}
                                            </p>
                                        </div>
                                        <button 
                                            onClick={handleCloseErrorAlert}
                                            style={{
                                                background: 'none',
                                                border: 'none',
                                                color: '#b91c1c',
                                                cursor: 'pointer',
                                                padding: '0.25rem',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                borderRadius: '6px'
                                            }}
                                            className="hover-bg-red"
                                        >
                                            <X size={20} />
                                        </button>
                                    </div>
                                )}

                                {showReservationForm && (
                                    <form 
                                        onSubmit={handleCreateReservation} 
                                        className="fade-in"
                                        style={{ 
                                            marginBottom: '2rem', 
                                            padding: '1.5rem', 
                                            background: '#f9fafb', 
                                            borderRadius: '16px',
                                            display: 'flex',
                                            gap: '1rem',
                                            flexWrap: 'wrap',
                                            alignItems: 'flex-end',
                                            border: '1px solid #6366f1'
                                        }}
                                    >
                                        <div style={{ flex: '1', minWidth: '200px' }}>
                                            <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.9rem', fontWeight: '600' }}>Fecha</label>
                                            <input 
                                                type="date" 
                                                value={resFormData.date}
                                                onChange={e => setResFormData({...resFormData, date: e.target.value})}
                                                className="input"
                                                style={{ borderRadius: '12px' }}
                                                required
                                            />
                                        </div>
                                        <div style={{ flex: '1', minWidth: '150px' }}>
                                            <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.9rem', fontWeight: '600' }}>Hora</label>
                                            <input 
                                                type="time" 
                                                value={resFormData.time}
                                                onChange={e => setResFormData({...resFormData, time: e.target.value})}
                                                className="input"
                                                style={{ borderRadius: '12px' }}
                                                required
                                            />
                                        </div>
                                        <div style={{ flex: '1', minWidth: '150px' }}>
                                            <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.9rem', fontWeight: '600' }}>Personas</label>
                                            <input 
                                                type="number" 
                                                min="1"
                                                max="12"
                                                value={resFormData.guest_count}
                                                onChange={e => setResFormData({...resFormData, guest_count: e.target.value})}
                                                className="input"
                                                style={{ borderRadius: '12px' }}
                                                required
                                            />
                                        </div>
                                        <button 
                                            type="submit" 
                                            className="btn btn-primary"
                                            style={{ borderRadius: '12px', height: '46px' }}
                                        >
                                            {editingReservationId ? 'Actualizar Reserva' : 'Guardar Reserva'}
                                        </button>
                                    </form>
                                )}

                                <div style={{ display: 'grid', gap: '1rem' }}>
                                    {reservations.map(res => (
                                        <div 
                                            key={res.id} 
                                            style={{ 
                                                display: 'flex', 
                                                alignItems: 'center', 
                                                justifyContent: 'space-between',
                                                padding: '1.5rem',
                                                background: 'white',
                                                borderRadius: '16px',
                                                border: '1px solid #e5e7eb',
                                                flexWrap: 'wrap',
                                                gap: '1rem'
                                            }}
                                        >
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '1.5rem' }}>
                                                <div style={{ 
                                                    background: '#ecfdf5', 
                                                    color: '#059669', 
                                                    padding: '0.75rem', 
                                                    borderRadius: '12px',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center'
                                                }}>
                                                    <Calendar size={24} />
                                                </div>
                                                <div>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                                                        <span style={{ fontWeight: '700', fontSize: '1.1rem' }}>{formatDate(res.date)}</span>
                                                        <span style={{ color: '#9ca3af' }}>|</span>
                                                        <span style={{ fontWeight: '600', color: '#4f46e5' }}>{res.time.substring(0, 5)}</span>
                                                    </div>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                                                        <p style={{ color: '#6b7280', fontSize: '0.9rem' }}>{res.user?.name || 'Cliente'}</p>
                                                        {( () => {
                                                            const timer = getTimeRemaining(res.date, res.time);
                                                            if (!isReservationActive(res.date, res.time)) return null;
                                                            return (
                                                                <div style={{ 
                                                                    fontSize: '0.9rem', 
                                                                    color: 'white',
                                                                    background: timer.isEnding ? '#ef4444' : '#10b981', 
                                                                    padding: '2px 8px', 
                                                                    borderRadius: '6px',
                                                                    fontWeight: 'bold'
                                                                }}>
                                                                    {timer.text}
                                                                </div>
                                                            );
                                                        })()}
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '2rem' }}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', color: '#6b7280' }}>
                                                    <Users size={18} />
                                                    <span>{res.guest_count} personas</span>
                                                </div>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', color: '#6b7280' }}>
                                                    <MapPin size={18} />
                                                    <span>Mesa {res.table_ids.map(id => getTableName(id)).join(', ')}</span>
                                                </div>
                                                {isAdmin && (
                                                    <>
                                                        <button 
                                                            onClick={() => handleEditClick(res)}
                                                            style={{ 
                                                                background: '#f3f4f6', 
                                                                border: 'none', 
                                                                padding: '0.5rem', 
                                                                borderRadius: '8px', 
                                                                cursor: 'pointer',
                                                                color: '#4b5563'
                                                            }}
                                                        >
                                                            <Edit size={18} />
                                                        </button>
                                                        <button 
                                                            onClick={() => handleDeleteReservation(res)}
                                                            style={{ 
                                                                background: '#fee2e2', 
                                                                border: 'none', 
                                                                padding: '0.5rem', 
                                                                borderRadius: '8px', 
                                                                cursor: 'pointer',
                                                                color: '#ef4444'
                                                            }}
                                                        >
                                                            <Trash2 size={18} />
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                    {reservations.length === 0 && (
                                        <p style={{ textAlign: 'center', color: '#9ca3af', padding: '3rem', background: '#f9fafb', borderRadius: '16px' }}>
                                            No hay reservaciones.
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </main>

            {/* Modals and Overlays */}
            {suggestion && (
                <div style={{
                    position: 'fixed',
                    top: 0, left: 0, right: 0, bottom: 0,
                    backgroundColor: 'rgba(0,0,0,0.5)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    zIndex: 1000,
                    backdropFilter: 'blur(4px)'
                }}>
                    <div className="glass-panel" style={{
                        backgroundColor: 'white',
                        padding: '2.5rem',
                        borderRadius: '24px',
                        maxWidth: '450px',
                        width: '90%',
                        textAlign: 'center',
                        boxShadow: '0 25px 50px -12px rgba(0,0,0,0.25)',
                        border: '1px solid rgba(79, 70, 229, 0.2)'
                    }}>
                        <div style={{ 
                            fontSize: '3.5rem', 
                            marginBottom: '1rem'
                        }}>📅</div>
                        <h3 style={{ fontSize: '1.5rem', fontWeight: '800', marginBottom: '1rem', color: '#111827' }}>
                            ¡Tenemos un lugar para vos!
                        </h3>
                        <p style={{ color: '#4b5563', marginBottom: '2rem', fontSize: '1.1rem', lineHeight: '1.6' }}>
                            ¿Deseas confirmar este horario? <br/>
                            <strong style={{ color: '#4f46e5', fontSize: '1.2rem' }}>{suggestion.label}</strong> <br/>
                            para <strong style={{ color: '#111827' }}>{suggestion.guest_count} personas</strong>.
                        </p>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                            <button 
                                onClick={handleConfirmSuggestion}
                                className="btn btn-primary"
                                style={{
                                    padding: '1rem',
                                    borderRadius: '14px',
                                    fontSize: '1rem',
                                    fontWeight: '700',
                                    boxShadow: '0 10px 15px -3px rgba(79, 70, 229, 0.4)'
                                }}
                            >
                                ✅ Sí, deseo confirmar este horario
                            </button>
                            <button 
                                onClick={() => setSuggestion(null)}
                                className="btn btn-outline"
                                style={{
                                    padding: '1rem',
                                    borderRadius: '14px',
                                    fontSize: '1rem',
                                    color: '#6b7280',
                                    border: '1px solid #e5e7eb'
                                }}
                            >
                                No por ahora
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Delete Confirmation Modal */}
            {showDeleteModal && (
                <div style={{
                    position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
                    background: 'rgba(0,0,0,0.5)', display: 'flex', justifyContent: 'center', alignItems: 'center', zIndex: 1000
                }}>
                    <div style={{
                        background: 'white', padding: '2rem', borderRadius: '16px', maxWidth: '400px', width: '90%',
                        boxShadow: '0 10px 25px rgba(0,0,0,0.2)'
                    }}>
                        <h3 style={{ fontSize: '1.25rem', fontWeight: 'bold', marginBottom: '1rem' }}>Confirmar Eliminación</h3>
                        <p style={{ color: '#4b5563', marginBottom: '2rem' }}>
                            ¿Estás seguro de que deseas eliminar la reserva de <strong>{reservationToDelete?.user?.name}</strong> para el <strong>{formatDate(reservationToDelete?.date)}</strong> a las <strong>{reservationToDelete?.time}</strong>?
                        </p>
                        <div style={{ display: 'flex', gap: '1rem', justifyContent: 'flex-end' }}>
                            <button 
                                onClick={() => setShowDeleteModal(false)}
                                className="btn btn-outline"
                            >
                                Cancelar
                            </button>
                            <button 
                                onClick={confirmDelete}
                                style={{ background: '#ef4444', color: 'white', border: 'none', padding: '0.5rem 1rem', borderRadius: '8px', cursor: 'pointer', fontWeight: '600' }}
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Table Reservations Modal */}
            {showTableReservationsModal && (
                <div style={{
                    position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
                    background: 'rgba(0,0,0,0.5)', display: 'flex', justifyContent: 'center', alignItems: 'center', zIndex: 1000
                }}>
                    <div style={{
                        background: 'white', padding: '2rem', borderRadius: '16px', maxWidth: '500px', width: '90%',
                        boxShadow: '0 10px 25px rgba(0,0,0,0.2)', maxHeight: '80vh', overflowY: 'auto'
                    }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem' }}>
                            <h3 style={{ fontSize: '1.5rem', fontWeight: 'bold' }}>{selectedTableName}</h3>
                            <button onClick={() => setShowTableReservationsModal(false)} style={{ background: 'none', border: 'none', cursor: 'pointer' }}>
                                <X size={24} />
                            </button>
                        </div>
                        
                        <div style={{ display: 'grid', gap: '1rem' }}>
                            {selectedTableReservations.length === 0 ? (
                                <p style={{ textAlign: 'center', color: '#9ca3af' }}>No hay reservas para esta mesa.</p>
                            ) : (
                                selectedTableReservations.map(res => (
                                    <div key={res.id} style={{ padding: '1rem', background: '#f9fafb', borderRadius: '8px', border: '1px solid #e5e7eb' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                                            <span style={{ fontWeight: 'bold' }}>{formatDate(res.date)} - {res.time.substring(0, 5)}</span>
                                            <span style={{ color: '#6b7280' }}>{res.guest_count} personas</span>
                                        </div>
                                        <p style={{ color: '#6b7280', fontSize: '0.9rem' }}>Cliente: {res.user?.name || 'Usuario'}</p>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>

        <ChatBox />
        </>
    );
};

export default Dashboard;
