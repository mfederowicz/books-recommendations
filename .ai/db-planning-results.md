<conversation_summary>

<decisions>
1. Użytkownik decyduje o implementacji logowania z bezpiecznie hashowanym hasłem oraz zapisywaniem informacji o ostatnim logowaniu.
2. Rekomendacje to skrócony opis, przypisaną listę tagów, data utowrzenia rekomendacji.
3. Przed wysłaniem rekomendacji do openai w celu uzyskania vectora rekomendacji dane wejściowe należy poddać normalizacji i zapisać w tabeli recommendations_embeddings 
  (do openai wysyłany jest tylko znormalizowany opis, tagi używamy tylko do filtrowania wynikow) .
4. Tagi pobierane będą jako lista z bazy, przy czym użytkownik nie będzie miał możliwości ich modyfikacji.
5. Historia zmian ani logowanie operacji na rekomendacjach nie jest przewidziane na tym etapie.
6. W celu poprawy wydajności, planuje się stosowanie indeksów złożonych na kolumnach najczęściej wykorzystywanych przy wyszukiwaniu.
7. Kontroli dostępu (RLS) realizowana bedzie poprzez parametryzowanie zapytań z id zalogowanego usera.
8. Projekt bazy musi zapewniać integralność danych poprzez zastosowanie kluczy obcych i ograniczeń.
9. Na podstawie vectora uzytkownika+listy tagów możemy generować listę rekomendowanych książek
</decisions>

<matched_recommendations>
1. Dokument wymagań produktu (PRD) podkreśla potrzebę rejestracji, logowania i zarządzania rekomendacjami, co jest zgodne z obsługą użytkowników i operacjami na rekomendacjach.
2. Informacje o tech stacku (MySQL 8.x, PHP, Symfony) wskazują na konieczność stosowania ograniczeń, kluczy obcych oraz wydajnych zapytań.
3. Zalecenia dotyczące bezpieczeństwa (hashowanie haseł, rejestrowanie ostatniego logowania) oraz odpowiedniej optymalizacji (indeksy, cache) są bezpośrednio powiązane z omawianymi rozwiązaniami.
4. Idea wykorzystania widoków w MySQL odpowiada potrzebie uproszczenia zapytań oraz wdrożenia kontroli dostępu, zgodnie z dyskusją.
</matched_recommendations>

<database_planning_summary>
- Główne wymagania dotyczące schematu bazy danych:
  • Obsługa encji: users,users_profiles,users_failed_logins, recommendations,recommendations_embeddings,ebooks,ebooks_embeddings, oraz tags.
  • Zapewnienie bezpieczeństwa danych logowania poprzez bezpieczne hashowanie haseł i rejestrowanie ostatniego logowania, jeżeli użytkownik próbuje logować się nieprawidłowym hasłem korzystamy z mechanizmu throttlingu.
  • Przechowywanie kluczowych danych rekomendacji (tytuł, autor, opis, grafika, wektory książek w formacie JSON),wektory opisów wprowadzanych przez usera (w celu optymalizacji zapytań do openai),  oraz przypisanych tagów.
  • Dostarczenie statycznej listy tagów, z której użytkownik nie może wprowadzać zmian.

- Kluczowe encje i ich relacje:
  • users: encja przechowująca dane logowania, w tym hasło (w formie bezpiecznego hasha) i znacznik ostatniego logowania. - id,name,email,password,must_change_password,banned,suspended,active,created
  • users_profiles: encja przechowująca info o profilu jaki ma użytkownik (admin,user,guest). - id,name,active
  • users_failed_logins: encja przechowująca informacje o próbach nieprawidłowego logowania. - id,user_id,ip_address,attempted - timestamp kiedy próba logowania nastąpiła,type - typ logowania user,admin (domyślnie user)
  • users_success_logins: encja przechowująca informacje o prawidłowych logowaniach. - id,user_id,ip_address,user_agent,type - typ logowania user,admin (domyślnie user)
  • recommendations: encja zawierająca informacje o szukanych książkach – skrócony opis,lista tagów,data dodania,user_id,hash znormalizowanego opisu (do powiązania z tabelą recommendations_embeddings).
  • recommendations_embeddings: encja zawierająca informacje o wektorach pozyskanych na podstawie opisów wprowadzonych przez użytkowników – hash,description,embedding,create_at.
  • ebooks: encja zawierająca informacje o książkach – isbn,title,author,data dodania,data aktualizacji,liczba ofert,link do porównywarki.
  • ebooks_embeddings: encja zawierająca informacje o wektorach pozyskanych z openai – id,vector,payload_title,payload_author,payload_tags.
  • tags: encja zawierająca tagi - id,tag_name,ascii - wersja ascii żeby można było tagu używać przy urlach.
  • Relacja między rekomendacjami a tagami będzie obsługiwana przez tabelę łączącą (relacja wiele-do-wielu).

- Ważne kwestie dotyczące bezpieczeństwa i skalowalności:
  • Bezpieczne przechowywanie haseł i monitorowanie ostatniego logowania, mechanizm throttlingu przy 3 i dalszych próbach nieprawidłowego logowania(wydłużamy czas procesowania użytkownika).
  • Przy logowaniu użytkownika system dodatkowo sprawdza wybrane flagi konta użytkownika(active,banned,suspended) .
  • Utrzymanie integralności danych przy użyciu kluczy obcych i ograniczeń.
  • Wykorzystanie złożonych indeksów na kolumnach najczęściej wykorzystywanych przy wyszukiwaniu w celu zwiększenia wydajności.
  • W celu przyspieszenia wyszukiwania po wektorach zostanie użyta baza wektorowa: qdrant do której dane będą wrzucane po wstępnej obróbce.
  • kontrola dostęp (RLS) - zostanie zaimplementowana w ten sposób że pobieranie listy rekomendacji będzie zawężone w zapytaniach dla wybranego usera poprzez przekazywanie jego identyfikatora w parametrach where zapytania.
- 

</database_planning_summary>


</conversation_summary>
