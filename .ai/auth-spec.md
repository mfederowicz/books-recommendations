# Specyfikacja Architektury Modułu Autentykacji Użytkowników

Na podstawie wymagań z pliku `.ai/prd.md` (user stories US-001: Rejestracja, US-002: Logowanie, US-003: Resetowanie hasła, US-004: Wylogowanie) oraz stacku technologicznego z `.ai/tech-stack.md` (Symfony 8.x, PHP z Composerem, MySQL 8.x, frontend z HTMX, Tailwind CSS i Basecoat UI), poniżej przedstawiam szczegółową architekturę modułu rejestracji, logowania i odzyskiwania hasła. Architektura zapewnia zgodność z istniejącym działaniem aplikacji, w tym routingiem zdefiniowanym w `config/routes.yaml` (główna strona homepage renderowana przez `DefaultController::index`). 

Specyfikacja skupia się na modularności, bezpieczeństwie (zgodnym z Symfony Security) i integracji z frontendem opartym na HTMX dla dynamicznych interakcji bez pełnego SPA. Reset hasła (US-003) jest ograniczony do mechanizmu administracyjnego via konsola, co minimalizuje ekspozycję na frontend. 
Całość opiera się na standardowych mechanizmach Symfony 8.x, z rozszerzeniem encji User o pola z opcją remember me (email, password_hash, last_login, created_at, updated_at).

## 1. ARCHITEKTURA INTERFEJSU UŻYTKOWNIKA

### Zmiany w warstwie frontendu (strony, komponenty i layouty):

- **Tryb non-auth (użytkownik niezalogowany):** 
  - Główna strona (`/`, homepage z `DefaultController::index`) rozszerzona o centralny formularz logowania/rejestracji. Layout bazuje na istniejącym `templates/base.html.twig` z Tailwind CSS i Basecoat UI dla responsywnego designu (np. centered card z inputami). Nowy komponent: `auth-form.twig` (partial Twig) zawierający przełącznik między formularzem logowania (email + hasło) a rejestracji (email + hasło + potwierdzenie hasła). Brak dostępu do sekcji rekomendacji – po próbie nawigacji (np. via link) HTMX redirectuje do homepage z komunikatem &quot;Zaloguj się, aby kontynuować&quot;.
  - Rozszerzenie: Istniejąca `homepage.html.twig` o conditional rendering (na podstawie sesji Symfony) – jeśli nieautentykowany, renderuj pełny auth-form; jeśli autentykowany, ukryj i pokaż sekcję rekomendacji (formularz opisu + lista).

- **Tryb auth (użytkownik zalogowany):**
  - Po logowaniu (US-002): Przekierowanie do homepage z widocznym formularzem rekomendacji (z PRD US-005) na górze i listą rekomendacji poniżej. Nowy komponent: `user-menu.twig` (w nagłówku base.html.twig) z przyciskiem &quot;Wyloguj&quot; (link do endpointu logout) i profilem (email). Layout: Sticky header z menu, main content z sekcją rekomendacji (paginowana lista z Tailwind grid).
  - Rozszerzenie: `homepage.html.twig` o dynamiczne sekcje – po autentykacji, HTMX ładuje partial `recommendations-list.twig` via AJAX (bez reloadu strony).

- **Nowe elementy i rozszerzenia:**
  - Formularze: `login-form.twig` i `register-form.twig` (partials) z inputami (email: type=&quot;email&quot;, hasło: type=&quot;password&quot; z Tailwind stylingiem). Przyciski submit z HTMX attributes (hx-post do backend endpointów, hx-target=&quot;#response-area&quot; dla błędów/sukcesu).
  - Komponenty client-side: HTMX dla asynchronicznych submitów (np. hx-post=&quot;/login&quot; z hx-swap=&quot;innerHTML&quot;), bez JavaScriptu poza walidacją HTML5 (required, minlength=8 dla hasła). Basecoat UI dla toastów błędów (np. div z klasami alert-error).
  - Layouty: `base.html.twig` rozszerzony o sloty dla auth-state (np. {% if app.user %} ... {% else %} ... {% endif %}).

### Rozdzielenie odpowiedzialności (HTMX vs. Symfony backend):
- **Client-side (HTMX):** Obsługuje interakcje UI – walidacja frontendowa (długość hasła), dynamiczne ładowanie partials (np. po submit logowania, HTMX wysyła POST do `/login` i swapuje sekcję auth-form na rekomendacje). Nawigacja: Linki z hx-get do endpointów Symfony, bez pełnego SPA – backend renderuje HTML partials.
- **Server-side (Symfony):** Renderuje pełne strony (homepage) i partials (auth-form, errors). Integracja z autentykacją: Po sukcesie logowania, Symfony ustawia sesję i redirectuje/ładuje partial z danymi dostępnymi dla zalogowanych. Akcje użytkownika: Submity HTMX trafiają do controllerów, które walidują i zwracają JSON/HTML response (np. success: redirect, error: partial z błędami).
- Integracja: HTMX hx-headers z CSRF tokenem (z Symfony Twig), backend weryfikuje security context.

### Walidacja i komunikaty błędów:
- Frontend: HTML5 (pattern dla email, minlength=8 dla hasła), JS-free via HTMX (błędy zwracane jako partial z Tailwind alertami: &quot;Nieprawidłowy email&quot; lub &quot;Hasła nie pasują&quot;).
- Backend-driven: Symfony waliduje (np. @Assert\Email, @Assert\Length), zwraca partial z błędami w hx-target (np. &lt;div class=&quot;error&quot;&gt;Błędny login lub hasło&lt;/div&gt;). Scenariusze: Nieistniejący email (rejestracja: &quot;Email zajęty&quot;), słabe hasło (&quot;Minimum 8 znaków&quot;), błędne logowanie (&quot;Nieprawidłowe dane&quot;).

### Obsługa scenariuszy:
- Rejestracja (US-001): Submit HTMX do `/register` – sukces: auto-logowanie i ładowanie rekomendacji; błąd: wyświetl partial błędu bez reloadu.
- Logowanie (US-002): Submit do `/login` – sukces: sesja 12h (via Symfony remember-me), ładowanie partial rekomendacji; błąd: toast + retry formularza. Sesja utrzymywana via cookies.
- Wylogowanie (US-004): Link hx-post do `/logout` – czyści sesję, HTMX swapuje na non-auth layout (tylko login form).
- Reset hasła (US-003): Brak frontendu – tylko konsola (nieeksponowane UI).

## 2. LOGIKA BACKENDOWA

### Struktura endpointów API i modeli danych:
- **Endpointy (rozszerzenie routes.yaml):**
  - POST `/register` (AuthController::register) – tworzy User, haszuje hasło, zapisuje do DB.
  - POST `/login` (AuthController::login) – waliduje credentials, ustawia sesję, obsługuje throttling.
  - POST `/logout` (z Symfony security) – czyści sesję.
  - Brak API dla resetu (US-003) – tylko konsola `security:reset-user-passwd`.
- **Modele danych:** Rozszerzenie encji `Entity/User.php` (z pamięci: id, email UNIQUE, password_hash, last_login, created_at, updated_at). Dodano `Entity/UserFailedLogin.php` dla mechanizmu throttling. Walidacja via @Assert (Symfony Validator): Email\Valid, Password\Length(min=8), UniqeEntity(email).
- Zgodność z istniejącym: Homepage (`/`) w `DefaultController::index` sprawdza `if ($this->getUser())` i renderuje conditional partials; nie modyfikuj istniejących tras.

### Mechanizm walidacji danych wejściowych:
- Symfony Form: Nowe formy `RegisterUserAccount` (extends AbstractType) z polami email/hasło, bind do Request. Walidacja: @Assert w encji + form-&gt;isSubmitted() &amp;&amp; form-&gt;isValid().
- Dla HTMX: Response jako HTML partial (Twig) z błędami form-&gt;getErrors(), lub JSON dla AJAX (jeśli potrzeba, ale prefer HTML dla prostoty).

### Obsługa wyjątków:
- Custom exceptions: `AuthException` (extends Exception) dla błędów jak &quot;Email exists&quot; lub &quot;Invalid credentials&quot;. W controllery: try-catch, log via Symfony Logger, return Response z status 400 + partial błędu.
- Global: W `Kernel.php` listener na Security events (np. AuthenticationFailureEvent) dla błędów logowania. 
- Dla resetu hasła: Komenda konsolowa rzuca wyjątek jeśli email nie istnieje.

### Aktualizacja renderowania stron server-side:
- `DefaultController::index`: Dodaj check `$user = $this-&gt;getUser();` – jeśli null, render `homepage.html.twig` z auth-form partial; else, render z recommendations partial (fetch z Repository). Użyj Twig includes dla modularności. Routes.yaml bez zmian – tylko import nowych auth routes.

## 3. SYSTEM AUTENTYKACJI

### Wykorzystanie standardowych mechanizmów Symfony 8.x:
- **Konfiguracja:** Rozszerz `config/packages/security.yaml`: firewalls z form_login (provider: entity, user_provider: App\Security\UserProvider), remember_me (secret, lifetime: 12h dla US-002), logout (path: /logout). Access_control: /login i /register anonymous, reszta authenticated.
- **User entity:** Implementuje UserInterface, PasswordAuthenticatedUserInterface. Hashing via UserPasswordHasherInterface (auto-injected w controllery).
- **Rejestracja (US-001):** W AuthController::register – create User, hasher-&gt;hashPassword(), entityManager-&gt;persist(), auto-logowanie via $this-&gt;getUserAuthenticator()-&gt;authenticateUser().
- **Logowanie (US-002):** Symfony form_login obsługuje POST /login, walidacja via AuthenticationUtils. Po sukcesie: update last_login, redirect do / z sesją. **Throttling:** Maksymalnie 5 nieudanych prób, blokada na 15 minut, automatyczne czyszczenie po sukcesie.
- **Wylogowanie (US-004):** Standardowy logout z security.yaml – invalidate sesję, clear cookies.
- **Reset hasła (US-003):** Custom konsola `Command\ResetUserPasswordCommand` (extends AbstractCommand) – argumenty email/passwd, fetch User by email, hasher-&gt;hashPassword(), flush. Brak email resetu – tylko admin via CLI (zgodne z PRD).
- **Serwisy i kontrakty:**
  - Serwis: `AuthService` (injected) dla wspólnej logiki (hashing, validation).
  - Serwis: `LoginThrottlingService` (DDD via interface) dla mechanizmu throttling logowania.
  - Kontrakty: UserProvider (entity), PasswordHasherInterface, AuthenticationUtils, LoginThrottlingServiceInterface.
  - Bezpieczeństwo: CSRF protection w formach, rate-limiting via custom LoginThrottlingService, hashowanie BCrypt.
  - Event Listeners: LoginFailureListener (rejestracja nieudanych prób), LoginSuccessListener (czyszczenie po sukcesie).

### Kluczowe wnioski:
- Architektura jest modułowa: Frontend HTMX minimalizuje JS, backend Symfony obsługuje stan via sesje.
Zgodność z PRD: Sesja 12h, reset tylko admin, conditional rendering bez naruszania istniejącej strony domowej.
- Bezpieczeństwo: Standardowe Symfony (hashing, CSRF), custom throttling service, event-driven logging.
- Konfiguracja: Parametry throttling w `services.yaml` (max_attempts: 5, block_duration: 15min, reset_after: 60min).
- Rozszerzalność: Łatwa integracja z rekomendacjami (US-005+) via security context w controllerach.
