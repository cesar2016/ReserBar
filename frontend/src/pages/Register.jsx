import { useState, useContext } from 'react';
import { AuthContext } from '../context/AuthContext';
import { useNavigate, Link } from 'react-router-dom';
import { User, Mail, Lock, UserPlus, UtensilsCrossed } from 'lucide-react';
import { toast } from 'sonner';
import Spinner from '../components/Spinner';

const Register = () => {
    const [formData, setFormData] = useState({ name: '', email: '', password: '', confirmPassword: '' });
    const { register } = useContext(AuthContext);
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (formData.password !== formData.confirmPassword) {
            toast.error('Las contraseñas no coinciden');
            return;
        }

        setLoading(true);
        try {
            const success = await register(formData.name, formData.email, formData.password);
            if (success) {
                toast.success('¡Registro exitoso!');
                navigate('/dashboard');
            } else {
                toast.error('Error al registrarse');
            }
        } catch (err) {
            console.error(err);
            toast.error('Error al registrarse');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div style={{ 
            minHeight: '100vh', 
            display: 'flex', 
            alignItems: 'center', 
            justifyContent: 'center',
            background: '#f3f4f6',
            padding: '1rem'
        }}>
            <div className="fade-in glass-panel" style={{ 
                width: '100%', 
                maxWidth: '420px', 
                padding: '3rem 2rem', 
                borderRadius: 'var(--radius-2xl)',
                position: 'relative',
                overflow: 'hidden'
            }}>
                <div style={{ 
                    position: 'absolute', 
                    top: '-50%', 
                    left: '-50%', 
                    width: '200%', 
                    height: '200%', 
                    background: 'radial-gradient(circle, rgba(99,102,241,0.1) 0%, rgba(255,255,255,0) 70%)',
                    pointerEvents: 'none'
                }} />

                <div style={{ position: 'relative', zIndex: 1 }}>
                    <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '2rem' }}>
                        <div style={{ 
                            background: 'var(--primary-gradient)', 
                            padding: '1rem', 
                            borderRadius: '50%', 
                            boxShadow: '0 10px 15px -3px rgba(99, 102, 241, 0.4)',
                            color: 'white'
                        }}>
                            <UtensilsCrossed size={32} />
                        </div>
                    </div>

                    <h2 style={{ 
                        textAlign: 'center', 
                        marginBottom: '0.5rem', 
                        fontSize: '1.75rem', 
                        fontWeight: '800',
                        color: '#111827'
                    }}>
                        Crear Cuenta
                    </h2>
                    <p style={{ textAlign: 'center', color: '#6b7280', marginBottom: '2.5rem' }}>
                        Únete a ReserBar
                    </p>

                    <form onSubmit={handleSubmit}>
                        <div style={{ marginBottom: '1.25rem' }}>
                            <label style={{ display: 'block', marginBottom: '0.5rem', fontWeight: '600', fontSize: '0.9rem' }}>Nombre</label>
                            <div style={{ position: 'relative' }}>
                                <User size={18} style={{ position: 'absolute', left: '1rem', top: '50%', transform: 'translateY(-50%)', color: '#9ca3af' }} />
                                <input
                                    type="text"
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    required
                                    placeholder="Tu nombre"
                                    className="input"
                                    style={{ paddingLeft: '2.75rem', borderRadius: 'var(--radius-xl)' }}
                                />
                            </div>
                        </div>

                        <div style={{ marginBottom: '1.25rem' }}>
                            <label style={{ display: 'block', marginBottom: '0.5rem', fontWeight: '600', fontSize: '0.9rem' }}>Email</label>
                            <div style={{ position: 'relative' }}>
                                <Mail size={18} style={{ position: 'absolute', left: '1rem', top: '50%', transform: 'translateY(-50%)', color: '#9ca3af' }} />
                                <input
                                    type="email"
                                    value={formData.email}
                                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                    required
                                    placeholder="tu@email.com"
                                    className="input"
                                    style={{ paddingLeft: '2.75rem', borderRadius: 'var(--radius-xl)' }}
                                />
                            </div>
                        </div>

                        <div style={{ marginBottom: '1.25rem' }}>
                            <label style={{ display: 'block', marginBottom: '0.5rem', fontWeight: '600', fontSize: '0.9rem' }}>Contraseña</label>
                            <div style={{ position: 'relative' }}>
                                <Lock size={18} style={{ position: 'absolute', left: '1rem', top: '50%', transform: 'translateY(-50%)', color: '#9ca3af' }} />
                                <input
                                    type="password"
                                    value={formData.password}
                                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                    required
                                    placeholder="••••••••"
                                    className="input"
                                    style={{ paddingLeft: '2.75rem', borderRadius: 'var(--radius-xl)' }}
                                />
                            </div>
                        </div>

                        <div style={{ marginBottom: '2rem' }}>
                            <label style={{ display: 'block', marginBottom: '0.5rem', fontWeight: '600', fontSize: '0.9rem' }}>Confirmar Contraseña</label>
                            <div style={{ position: 'relative' }}>
                                <Lock size={18} style={{ position: 'absolute', left: '1rem', top: '50%', transform: 'translateY(-50%)', color: '#9ca3af' }} />
                                <input
                                    type="password"
                                    value={formData.confirmPassword}
                                    onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })}
                                    required
                                    placeholder="••••••••"
                                    className="input"
                                    style={{ paddingLeft: '2.75rem', borderRadius: 'var(--radius-xl)' }}
                                />
                            </div>
                        </div>

                        <button 
                            type="submit" 
                            className="btn btn-primary" 
                            style={{ width: '100%', borderRadius: 'var(--radius-xl)', padding: '1rem' }} 
                            disabled={loading}
                        >
                            {loading ? <Spinner size={20} color="#fff" /> : <><UserPlus size={20} /> Registrarse</>}
                        </button>
                    </form>

                    <div style={{ textAlign: 'center', marginTop: '1.5rem', fontSize: '0.9rem' }}>
                        <span style={{ color: '#6b7280' }}>¿Ya tienes cuenta? </span>
                        <Link to="/login" style={{ color: '#6366f1', fontWeight: '600', textDecoration: 'none' }}>
                            Ingresa
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Register;
