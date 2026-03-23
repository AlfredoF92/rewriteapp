# Language Learning Stories – Analisi e guida

## A cosa serve

Il plugin **Language Learning Stories** (LLS) permette di creare **storie per esercitarsi nella traduzione dall’italiano all’inglese**. Ogni storia è una sequenza di frasi: l’utente vede la frase in italiano, scrive (o detta) la traduzione in inglese, riceve feedback (consigli grammaticali e traduzioni alternative) e può riscrivere la frase prima di passare alla successiva. La storia si costruisce man mano che l’utente completa le frasi.

---

## Come funziona (flusso utente)

1. L’utente apre una **Storia** (es. `/storie/la-cicala-e-la-formica/`).
2. Vede **titolo**, **barra di progresso** e (opzionale) **immagine di apertura**.
3. Per ogni frase:
   - Vede la **frase in italiano** (con effetto typewriter).
   - Scrive o **detta** (microfono, Web Speech API) la traduzione in inglese.
   - Clicca **Continua**.
   - Vede **la sua risposta**, poi (con typewriter) **consigli grammaticali** e **traduzioni alternative**.
   - Può **riscrivere** la frase; il pulsante **Bravo! Vai avanti** si abilita quando il testo coincide con una delle traduzioni accettate.
   - Cliccando **Bravo**, la frase in inglese viene aggiunta alla storia (con typewriter) e, se configurata, appare l’**immagine** dopo quella posizione.
4. Al termine delle frasi appare **Storia completata!** con opzione **Ricominciare la storia**.
5. Il **progresso** (quante frasi completate + HTML della storia) viene salvato via AJAX **solo per utenti loggati** (`_lls_progress_{post_id}` in user meta).

---

## Back-end (PHP)

### Post type e dati

- **Post type:** `lls_story`  
  - Slug URL: `/storie/{slug}/`  
  - Supporta: titolo, editor, thumbnail.  
  - `show_in_rest: true` (editabile con Gutenberg).

- **Meta della storia:**
  - `_lls_opening_image_id` – ID attachment immagine di apertura.
  - `_lls_sentences` – Array di frasi. Ogni elemento:
    - `text_it` – Frase in italiano (HTML consentito).
    - `main_translation` – Traduzione principale (mostrata nella storia).
    - `alt1`, `alt2` – Traduzioni alternative (accettate per “Bravo”).
    - `grammar` – Consigli grammaticali (HTML).
  - `_lls_images` – Array di `{ position, attachment_id }`: immagini mostrate “dopo la frase #position”.

### Admin (schermata modifica storia)

- **Meta box “Informazioni Storia”:** scelta immagine di apertura (Media Library).
- **Meta box “Frasi della Storia”:**
  - Lista **frasi** trascinabili (jQuery UI Sortable), espandibili:
    - Frase IT, traduzione principale, 3 alternative, consigli grammaticali.
    - Pulsante “Inserisci box immagine qui” (crea un box immagine con posizione = #frase).
  - Lista **box immagini** con posizione (dopo frase #) e upload.
  - Pulsanti: **Aggiungi Frase**, **Importa CSV**, **Esporta CSV** (UTF-8 BOM).
- Salvataggio: `save_post` → sanitizzazione (`wp_kses_post` per testo/HTML, `sanitize_text_field` per alternative) e `update_post_meta` / `delete_post_meta`.
- **Nonce:** `lls_story_nonce` per il salvataggio; `lls_admin_nonce` in `llsAdmin` per eventuali AJAX admin.
- All’attivazione / cambio versione: flush rewrite e opzione `lls_plugin_version`.
- **Storia campione:** alla prima visita admin, se non esiste già “La Cicala e la Formica”, viene creata una storia con 5 frasi e consigli grammaticali dettagliati (`maybe_create_sample_story`).

### Template e asset front-end

- **Template:** `template_include` forza `templates/single-lls_story.php` per `is_singular('lls_story')`. Il template fa solo `get_header()`, un `<main>` con `#lls-story-root` e “Caricamento…”, e `get_footer()`; tutto il contenuto è iniettato da JS.
- **Script/CSS** caricati solo su singola storia:
  - Google Font **Lora**.
  - `lls-frontend.css`, `lls-frontend.js` (dipende da jQuery).
  - `wp_localize_script('lls-frontend-script', 'llsStory', …)` passa: `storyId`, `title`, `sentences`, `images` (con URL), `openingImageUrl`, `progress`, `ajaxUrl`, `nonce`.

### AJAX

- **Action:** `lls_save_progress` (registrata per utenti loggati e non; la logica salva solo se `is_user_logged_in()`).
- **Payload:** `story_id`, `completed` (indice frasi completate), `story_text` (HTML della storia).
- **Nonce:** `lls_save_progress`.
- Salvataggio in `_lls_progress_{story_id}` come array `['completed' => int, 'story_text' => string]`.

---

## Front-end (JS e CSS)

### App a stato unico (jQuery)

- **Stato:** `completedIndex`, `storyHtml`, `showFeedback`, `userTranslation`.
- **Render:** una sola funzione `render()` che svuota `#lls-story-root` e, in base allo stato, scrive:
  - Header (titolo, barra progresso, contatore).
  - Immagine di apertura (se presente).
  - Blocco “storia costruita” (`state.storyHtml`).
  - Se storia finita: messaggio di completamento + pulsante “Ricominciare”.
  - Se c’è una frase corrente:
    - **Fase 1 (non feedback):** “Prossima frase” (typewriter italiano), textarea + microfono, “Continua”.
    - **Fase 2 (feedback):** “La tua risposta”, typewriter consigli grammaticali, typewriter alternative, prompt “Ora riscrivi…”, textarea riscrittura + microfono, “Bravo! Vai avanti”.
- **Typewriter:** parole in sequenza (anche con HTML nei consigli/alternative, tokenizzando tag vs testo).
- **Microfono:** Web Speech API (en-US), “mantieni premuto” per dettare; il testo viene aggiunto nella textarea.
- **Match per “Bravo”:** normalizzazione (minuscolo, rimozione punteggiatura/spazi multipli) e confronto con `main_translation` e `alt1/2/3`.
- **Lettura ad alta voce:** click su un paragrafo della storia già costruita → `speechSynthesis` con voce inglese preferita (es. “natural”, “Google”, “Samantha”) e rate ridotto.
- **Salvataggio progresso:** `saveProgress()` chiama `lls_save_progress` via `$.post` (senza gestione errore in UI).

### CSS

- **Front-end:** tema “libro” (Lora, colori tipo inchiostro/carta), barra progresso, box feedback, animazioni fade, layout responsive; in `lls-frontend.css` c’è un riferimento a `localhost` per uno sfondo (da rendere configurabile o rimuovere).
- **Admin:** liste frasi/immagini, card espandibili, drag handle, modal anteprima import CSV.

---

## Cosa si può migliorare

### Sicurezza e robustezza

- **Progresso per utenti non loggati:** oggi non viene salvato; si può usare un token in `sessionStorage`/cookie e salvare in opzione o tabella “progressi anonimi” con scadenza, così il progresso sopravvive al refresh anche senza account.
- **Nonce e capability:** verificare che tutte le chiamate AJAX e i form usino nonce e che l’AJAX di salvataggio progresso controlli `edit_post` o un capability dedicato se si espone a ruoli personalizzati.
- **Escape in front-end:** in JS si usano `escapeHtml` e `escapeAttr`; assicurarsi che ogni contenuto proveniente da `llsStory` (titolo, frasi, grammar, URL immagini) passi da queste funzioni prima di essere inserito nel DOM.
- **Content Security Policy:** se il sito usa CSP, verificare che `speechSynthesis` e eventuali blob/worker non vengano bloccati.

### UX e accessibilità

- **Messaggio “nessun progresso senza login”:** avvisare l’utente che senza account il progresso non viene salvato (e magari offrire link a registrazione/login).
- **Feedback salvataggio:** mostrare “Progresso salvato” / “Errore salvataggio” dopo la chiamata AJAX di progresso.
- **Velocità typewriter:** renderla configurabile (es. slider “Lento / Normale / Veloce”) o pulsante “Mostra tutto” per saltare l’animazione.
- **Accessibilità:**
  - Associazione label/textarea e annunci per screen reader quando cambia “Prossima frase” / “Riscrivi”.
  - Stato “in ascolto” del microfono annunciato (già c’è `aria-live` su un elemento).
  - Evitare che il typewriter blocchi il focus: consentire Tab e lettura da screen reader anche durante l’animazione.

### Funzionalità

- **Valutazione “morbida”:** oltre al match esatto, accettare traduzioni “simili” (es. distanza di Levenshtein o confronto a livello di parole) e mostrare “Quasi! Controlla punteggiatura/articoli” invece di richiedere solo match esatto per “Bravo”.
- **Risposta corretta se sbagliata:** dopo un certo numero di tentativi o un timer, mostrare la traduzione suggerita e permettere di copiarla/leggerla prima di andare avanti.
- **Lista storie:** pagina archivio “Storie” (`has_archive => true` o shortcode) con elenco delle storie e eventuale indicatore “Completata” / “In corso” per utenti loggati.
- **Immagini nella storia:** attributi `alt` significativi (campo in admin per ogni box immagine) per accessibilità e SEO.

### Codice e manutenzione

- **Versione script/style:** usare `LLS_PLUGIN_VERSION` (o `filemtime`) invece di stringa fissa `'0.1.0'` per cache busting.
- **Rimuovere/localizzare localhost:** in `lls-frontend.css` sostituire l’URL di background con variabile CSS o filtro WordPress (es. `lls_story_background_image`).
- **Modularità JS:** spezzare `lls-frontend.js` in moduli (typewriter, mic, state, render, save) o almeno in funzioni più piccole per facilitare test e estensioni.
- **i18n:** le stringhe in `lls-admin.js` (es. “Frase #”, “Modifica”, “Chiudi”) sono in italiano; passarle tutte da `llsAdmin.i18n` (come già fatto in parte) per supporto multilingua admin.
- **Test:** aggiungere test PHP (es. per `save_story_meta`, sanitizzazione, creazione storia campione) e, se possibile, test JS per la logica di normalizzazione/match e typewriter.

### Performance

- **Caricamento dati:** le frasi e le immagini sono passate tutte in `llsStory`; per storie molto lunghe si può valutare di caricare le frasi a blocchi via AJAX (es. “pagina” di 10 frasi).
- **Font:** caricare Google Font solo su `is_singular('lls_story')` (già fatto); eventualmente preconnect per `fonts.googleapis.com` per ridurre LCP.

---

## Riepilogo file

| File | Ruolo |
|------|--------|
| `language-learning-stories.php` | Bootstrap, post type, meta box, save, template redirect, enqueue, AJAX, storia campione |
| `templates/single-lls_story.php` | Template singola storia (header/footer + contenitore per JS) |
| `assets/lls-admin.js` | UI admin: frasi, immagini, sortable, import/export CSV, modal anteprima |
| `assets/lls-admin.css` | Stili admin (card, handle, modal) |
| `assets/lls-frontend.js` | App storia: stato, render, typewriter, microfono, match, salvataggio progresso, TTS |
| `assets/lls-frontend.css` | Stili front-end (tema libro, progresso, feedback, animazioni) |

Se vuoi, il passo successivo può essere implementare una o due di queste migliorie (es. salvataggio progresso anonimo + messaggio utente, o correzione URL background in CSS) direttamente nel codice.
