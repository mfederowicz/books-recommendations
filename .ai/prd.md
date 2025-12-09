# Dokument wymagań produktu (PRD) - Books recommender
## 1. Przegląd produktu
Aplikacja Books recommender to narzędzie mające na celu uproszczenie procesu znajdowania ciekawych książek do przeczytania. W MVP skupiamy się na podstawowych funkcjonalnościach, takich jak tworzenie, odczytywanie, przeglądanie i usuwanie rekomendacji książkowych, a także prosty system zarządzania kontem użytkownika oraz profil użytkownika z zapisanymi preferencjami.

## 2. Problem użytkownika
Użytkownik często zmaga się z trudnością wyboru książki, która odpowiada jego gustom oraz aktualnym potrzebom. Proces poszukiwania wartościowych rekomendacji bywa czasochłonny i nieintuicyjny, co może zniechęcać do korzystania z tradycyjnych źródeł rekomendacji.

## 3. Wymagania funkcjonalne
- Umożliwienie użytkownikowi rejestracji konta oraz logowania, co wiąże się z bezpiecznym dostępem i uwierzytelnianiem.
- Intuicyjny formularz do wprowadzania opisu książki, gdzie opis musi mieć od 30 do 500 znaków.
- Implementacja algorytmu rekomendacji opartego na funkcji cosineSimilarity, który dopasowuje opis podany przez użytkownika do prewyliczonych wektorów książek.
- Prezentacja wyników w formie listy zawierającej tytuł, autora, skrócony opis oraz okładkę książki, z możliwością paginacji przy wynikach przekraczających 10 pozycji.
- System walidacji danych, który wymaga, aby każda rekomendacja posiadała co najmniej 2 przypisane tagi.
- Mechanizm w tle odpowiedzialny za pobieranie nowych wektorów książek, umożliwiający regularne aktualizowanie rekomendacji.
- W celu przyspieszenia dobierania rekomendacji książkowych zostanie użyta baza wektorowa qdrant.

## 4. Granice produktu
- Aplikacja nie będzie obsługiwała współdzielenia rekomendacji między różnymi użytkownikami.
- Nie przewiduje się bogatej obsługi multimediów, takich jak zdjęcia okładek, poza ich wyświetlaniem w wynikach.
- Udostępnianie rekomendacji w mediach społecznościowych wykracza poza zakres MVP.

## 5. Historyjki użytkowników
### US-001: Rejestracja
- **Tytuł:** Rejestracja konta
- **Opis:** Użytkownik powinien być w stanie zarejestrować się do aplikacji, co umożliwi dostęp do spersonalizowanych rekomendacji i zapisanych preferencji.
- **Kryteria akceptacji:**
  - Użytkownik może utworzyć konto podając wymagane dane (eamil i hasło).
  - Proces logowania jest zabezpieczony przy użyciu bezpiecznych metod uwierzytelniania.
  - System weryfikuje poprawność danych przy logowaniu.
  - Użytkownik może zarejestrować tylko jedno konto na adres emial, w przypadku istniejącego konta, podajemy odpowiedni komunikat błędu

### US-002: Logowanie
- **Tytuł:** Logowanie do konta
- **Opis:** Użytkownik powinien być w stanie zalogować się do aplikacji, co umożliwi dostęp do spersonalizowanych rekomendacji i zapisanych preferencji.
- **Kryteria akceptacji:**
    - **Status: ✓** - Podanie loginu i hasła przekierowauje do formularza rekomendacji na górze strony i listy rekomendacji poniżej.
    - **Status: ✓** - Błędne dane zwracają komunikat o niepowodzeniu logowania.
    - **Status: ✓** - Po poprawnym logowaniu sesja utrzymuje się przez min 12h.
    - **Status: ✓** - Wielokrotne próby logowania ze złym hasłem powinny skutkować zapisem takiego eventu w tabeli users_failed_logins, oraz blokadę logowania na wybrany okres czasu.
    - **Status: ✓** - throttling logowania z maksymalnie 5 próbami, blokadą na 15 minut, automatycznym czyszczeniem po pomyślnym logowaniu.

### US-003: Resetowanie hasła
- **Tytuł:** Resetowanie hasła do konta
- **Opis:** Reset hasła do konta możliwe jest tylko przez administratora.
- **Kryteria akceptacji:**
    - **Status: ✓** - Reset hasła realizowany jest przez komendę: security:reset-user-passwd email passwd.
    - **Status: ✓** - Po resecie hasła możliwe jest zalogowanie bez potrzeby korzystania ze skrzynki email.

### US-004: Wylogowanie
- **Tytuł:** Wylogowanie z konta
- **Opis:** Użytkownik powinien być w stanie wylogować się z aplikacji.
- **Kryteria akceptacji:**
    **Status: ✓** - Wylogowanie usuwa wszelkie ślady sesji użytkownika.
    **Status: ✓** - Po wylogowaniu widać tylko formularz logowania, nie można dostać się do innych sekcji.

### US-005: Wprowadzanie opisu książki
- **Tytuł:** Dodawanie opisu książki w celu uzyskania rekomendacji
- **Opis:** Użytkownik wprowadza opis książki w intuicyjnym formularzu, którego długość wynosi od 30 do 500 znaków.
- **Kryteria akceptacji:**
  - Użytkownik po zalogowaniu (US-002) może wypełnić formularz dodania nowej rekomendacji.
  - Formularz nie przyjmuje opisu krótszego niż 30 znaków ani dłuższego niż 500 znaków.
  - Oprócz opisu użytkownik wybiera z listy tagów min 5-15 tagów.
  - Wprowadzenie opisu i wybranie listy tagów, po normalizacji opisu sprawdzamy czy taka rekomendacja już nie istnieje(encja recommendations_embeddings), jeżeli nie istnieje pobieranany jest zestaw danych z openai i zapisywany w bazie.

### US-006: Wyświetlanie rekomendacji
- **Tytuł:** Prezentacja rekomendacji książkowych
- **Opis:** Po wprowadzeniu opisu książki, użytkownik otrzymuje listę rekomendacji zawierającą tytuł, autora, skrócony opis oraz grafikę okładki książki. Wyniki są paginowane, gdy ich liczba przekracza 10 pozycji.
- **Kryteria akceptacji:**
  - System wyświetla wyniki w czytelnej formie.
  - Użytkownik może przechodzić między stronami wyników.
  - Rekomendacje mogą być wyświetlane tylko po zalogowaniu (US-002)

### US-007: Usuwanie rekomendacji książkowej użytkownika
- **Tytuł:** Usuwanie rekomendacji książkowych
- **Opis:** Użytkownik może moderować swoją listę rekomendacji.
- **Kryteria akceptacji:**
    - Użytkownik może usunąć posiadaną już rekomendację.
    - Usunięcie rekomendacji możliwe jest tylko po zalogowaniu (US-002)

### US-008: Automatyczna aktualizacja wektorów książek
- **Tytuł:** Aktualizacja danych rekomendacji w tle
- **Opis:** System automatycznie pobiera nowe wektory książek w tle, umożliwiając aktualizację wyników rekomendacji przy kolejnych wizytach użytkownika.
- **Kryteria akceptacji:**
  - Proces aktualizacji odbywa się bez zakłócania pracy użytkownika.
  - Nowe wektory wpływają na trafność rekomendacji przy następnych wyszukiwaniach.
  - Proces aktualizacji realizowany jest przez komendy z poziomu konsoli, można je uruchomić ręcznie, lub w przyszłości z poziomu crona

## 6. Metryki sukcesu
- Co najmniej 90% użytkowników posiada wypełnione preferencje w swoich profilach.
- Co najmniej 75% użytkowników generuje 3 lub więcej rekomendacji na rok.
- Trafność rekomendacji mierzona funkcją cosineSimilarity osiąga założony poziom dokładności.
- Użyteczność interfejsu mierzona poprzez testy UX, potwierdzająca intuicyjność formularza oraz przejrzystość prezentacji wyników.
- Stabilność systemu w tle odpowiedzialnego za aktualizację wektorów, zapewniająca terminowe i bezproblemowe odświeżanie wyników.

