import { useEffect, useMemo, useRef, useState } from 'react';
import { linguaggio } from './linguaggi.js';

/**
 * Un campo di codice: righe numerate, errori segnati dove sono, e un pulsante
 * che rimette in ordine.
 *
 * È il gutter del pannello JSON portato dove si SCRIVE. Là i numeri di riga
 * servono a leggere un documento che il CMS ha generato; qui servono a chi il
 * codice lo sta battendo, che è il momento in cui un numero di riga vale di
 * più - un errore senza riga è una caccia.
 *
 * Il linguaggio lo dice chi usa il campo (`lingua`), e da lì arrivano sia la
 * formattazione sia le regole: stanno in `linguaggi.js`, una volta per tutti.
 *
 * Il gutter è un elemento a parte e non un fondo disegnato: i numeri devono
 * poter diventare rossi. Scorre insieme alla textarea perché condividono
 * `line-height` e perché lo scorrimento glielo si copia a mano - due elementi
 * che scorrono da soli, prima o poi, si scollano.
 */
export function CodeArea({
	value = '',
	onChange,
	lingua = 'xhtml',
	disabled = false,
	compact = false,
	label,
	/* Tasti di chi ci mette il campo: il pannello JSON chiude con Esc e applica
	   con Cmd+Invio, e quelle sono decisioni sue, non di un campo di codice.
	   Parlano per primi; se non consumano l'evento, resta il Tab di qui. */
	onKeyDown,
}) {
	const L = linguaggio(lingua);
	const testo = value ?? '';
	const gutter = useRef(null);
	const area = useRef(null);
	const [scritto, setScritto] = useState(false);

	const righe = useMemo(() => testo.split('\n'), [testo]);
	const esito = useMemo(() => {
		try {
			return L.valida(testo);
		} catch (e) {
			/* Un validatore che esplode non deve portarsi dietro il campo: chi
			   sta scrivendo ha diritto a continuare a scrivere. */
			return { ok: true, errori: [], rotto: e.message };
		}
	}, [L, testo]);

	const righeRotte = useMemo(
		() => new Set((esito.errori || []).map((e) => e.line).filter(Boolean)),
		[esito]
	);

	/* Il gutter segue la textarea e non viceversa: è lei che ha il cursore. */
	const segui = () => {
		if (gutter.current && area.current) gutter.current.scrollTop = area.current.scrollTop;
	};
	useEffect(segui, [testo]);

	const formatta = () => {
		if (!L.formatta) return;
		try {
			const ordinato = L.formatta(testo);
			if (ordinato !== testo) onChange(ordinato);
			setScritto(true);
			setTimeout(() => setScritto(false), 1200);
		} catch (e) {
			/* Non si formatta quello che non si riesce a leggere. Il messaggio
			   dell'errore c'è già sotto: qui basta non fare niente, invece di
			   sostituire il testo con qualcosa di peggio. */
		}
	};

	return (
		<div className={'code-area' + (compact ? ' compact' : '')}>
			<div className="code-area-head">
				<span className="code-area-lingua">{label || L.nome}</span>
				{L.formatta ? (
					<button
						type="button"
						className="icon-btn"
						title={'Rimetti in ordine il ' + L.nome}
						disabled={disabled || !testo.trim()}
						tabIndex={-1}
						onMouseDown={(e) => e.preventDefault()}
						onClick={formatta}
					>
						<span className="material-symbols-outlined">{scritto ? 'check' : 'format_align_left'}</span>
					</button>
				) : null}
				<span className={'code-area-stato ' + (esito.ok ? 'ok' : 'rotto')}>
					<span className="material-symbols-outlined">{esito.ok ? 'check_circle' : 'error'}</span>
				</span>
			</div>

			<div className="code-area-corpo">
				<div className="line-numbers" ref={gutter} aria-hidden="true">
					{righe.map((_, i) => (
						<span key={i} className={righeRotte.has(i + 1) ? 'line-error' : undefined}>
							{i + 1}
						</span>
					))}
				</div>
				<textarea
					ref={area}
					className="code-area-testo code-font"
					value={testo}
					spellCheck={false}
					disabled={disabled}
					onScroll={segui}
					onChange={(e) => onChange(e.target.value)}
					/* Tab dentro il codice RIENTRA, non salta al campo dopo: in un
					   campo di codice la tabulazione è un carattere, e chi scrive
					   se l'aspetta. Shift+Tab esce, così la tastiera non resta
					   prigioniera. */
					onKeyDown={(e) => {
						if (onKeyDown) onKeyDown(e);
						if (e.defaultPrevented) return;
						if (e.key !== 'Tab' || e.shiftKey) return;
						e.preventDefault();
						const t = e.target;
						const prima = testo.slice(0, t.selectionStart);
						const dopo = testo.slice(t.selectionEnd);
						const punto = t.selectionStart + 1;
						onChange(prima + '\t' + dopo);
						requestAnimationFrame(() => { t.selectionStart = t.selectionEnd = punto; });
					}}
				/>
			</div>

			{!esito.ok && esito.errori.length ? (
				<ul className="code-area-errori">
					{esito.errori.map((e, i) => (
						<li key={i}>
							{e.line ? <b>riga {e.line}</b> : <b>{L.nome}</b>} — {e.message}
						</li>
					))}
				</ul>
			) : null}
		</div>
	);
}
