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
	/* Sola lettura: stesso gutter, stesse regole, ma il testo si guarda e basta.
	   Lo usa il pannello JSON, che mostra il documento come verrebbe scritto sul
	   file. Li' il testo e' un <pre> e non un <textarea>, cosi' la riga rotta si
	   puo' evidenziare anche nel corpo e non solo nel numero. */
	readOnly = false,
	/* Errori che vengono da FUORI: il pannello JSON ne ha di suoi - lo schema,
	   un campo che non va bene - e non sono errori di sintassi, quindi il
	   validatore del linguaggio non li troverebbe mai. Si sommano ai propri:
	   sono due domande diverse sullo stesso testo, e chi guarda le vuole tutte e
	   due sullo stesso gutter. */
	errori: errioriEsterni,
	/* Chi ha gia' una testata sua non ne vuole una seconda. */
	hideHead = false,
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

	const tutti = useMemo(
		() => (esito.errori || []).concat(errioriEsterni || []),
		[esito, errioriEsterni]
	);
	const righeRotte = useMemo(
		() => new Set(tutti.map((e) => e.line).filter(Boolean)),
		[tutti]
	);

	/* Il gutter segue la textarea e non viceversa: è lei che ha il cursore. */
	const segui = () => {
		if (gutter.current && area.current) gutter.current.scrollTop = area.current.scrollTop;
	};
	useEffect(segui, [testo]);

	/* «Correggi» compare SOLO quando c'e' qualcosa di rotto, e solo se il
	   linguaggio sa come rimetterlo a posto. Un pulsante che c'e' sempre invita
	   a premerlo anche quando non serve; e uno che c'e' e non fa niente e' la
	   cosa che si stava correggendo. */
	const correggi = () => {
		if (!L.correggi) return;
		const rimesso = L.correggi(testo);
		if (rimesso !== testo) onChange(rimesso);
	};

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
		<div className={'code-area' + (compact ? ' compact' : '') + (readOnly ? ' letta' : '')}>
			{hideHead ? null : (
			<div className="code-area-head">
				<span className="code-area-lingua">{label || L.nome}</span>
				{L.correggi && !esito.ok ? (
					<button
						type="button"
						className="icon-btn"
						title={'Rimetti in regola il ' + L.nome}
						disabled={disabled}
						tabIndex={-1}
						onMouseDown={(e) => e.preventDefault()}
						onClick={correggi}
					>
						<span className="material-symbols-outlined">healing</span>
					</button>
				) : null}
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
				<span className={'code-area-stato ' + (tutti.length ? 'rotto' : 'ok')}>
					<span className="material-symbols-outlined">{tutti.length ? 'error' : 'check_circle'}</span>
				</span>
			</div>
			)}

			<div className="code-area-corpo">
				<div className="line-numbers" ref={gutter} aria-hidden="true">
					{righe.map((_, i) => (
						<span key={i} className={righeRotte.has(i + 1) ? 'line-error' : undefined}>
							{i + 1}
						</span>
					))}
				</div>
				{readOnly ? (
					<pre ref={area} className="code-area-testo" onScroll={segui}>
						{righe.map((r, i) => (
							<span key={i} className={righeRotte.has(i + 1) ? 'line-error' : undefined}>
								{r + '\n'}
							</span>
						))}
					</pre>
				) : (
				<textarea
					ref={area}
					className="code-area-testo"
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
				)}
			</div>

			{tutti.length ? (
				<ul className="code-area-errori">
					{tutti.map((e, i) => (
						<li key={i}>
							{e.line ? <b>riga {e.line}</b> : <b>{L.nome}</b>} — {e.message}
						</li>
					))}
				</ul>
			) : null}
		</div>
	);
}
