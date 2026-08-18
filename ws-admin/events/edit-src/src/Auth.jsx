import { useEffect, useRef } from 'react';
import { GOOGLE_CLIENT_ID, SAVE_EVENT_URL } from './config.js';

// Login Google Identity per il salvataggio sul web. Non loggato → bottone Google;
// loggato → email + ruolo + logout. Il token (credential) e il ruolo salgono al parent.
export default function Auth({ user, onLogin, onLogout }) {
  const btnRef = useRef(null);

  useEffect(() => {
    if (user) return; // loggato: nessun bottone da renderizzare
    let tries = 0;
    let cancelled = false;
    const init = () => {
      if (cancelled) return;
      const g = window.google?.accounts?.id;
      if (!g) {
        if (tries++ < 40) setTimeout(init, 150); // attende il caricamento dello script GSI
        return;
      }
      g.initialize({
        client_id: GOOGLE_CLIENT_ID,
        callback: async (resp) => {
          const cred = resp?.credential;
          if (!cred) return;
          try {
            const body = new URLSearchParams({ action: 'auth', credential: cred });
            const res = await fetch(SAVE_EVENT_URL, {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: body.toString(),
            });
            const out = await res.json();
            if (out?.email) onLogin(cred, { email: out.email, role: out.role, uid: out.uid });
            else onLogin(cred, { email: '(non in users.xml)', role: out?.role || 'verified-visitor' });
          } catch {
            onLogin(cred, { email: '(verifica ruolo non riuscita)', role: 'verified-visitor' });
          }
        },
      });
      if (btnRef.current) {
        btnRef.current.innerHTML = '';
        g.renderButton(btnRef.current, { type: 'standard', theme: 'outline', size: 'medium', text: 'signin', shape: 'pill' });
      }
    };
    init();
    return () => { cancelled = true; };
  }, [user, onLogin]);

  if (user) {
    return (
      <div className="auth-user" title={`Ruolo: ${user.role}`}>
        <span className="material-symbols-outlined">account_circle</span>
        <span className="auth-email">{user.email}</span>
        <span className="auth-role">{user.role}</span>
        <button type="button" className="icon-btn" onClick={onLogout} title="Esci">
          <span className="material-symbols-outlined">logout</span>
        </button>
      </div>
    );
  }
  return <div ref={btnRef} className="auth-btn" />;
}
