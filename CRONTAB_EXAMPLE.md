# Przykład konfiguracji Cron dla aktualizacji rekomendacji

## Harmonogram aktualizacji rekomendacji

### Zalecany harmonogram:

```bash
# Codzienna aktualizacja rekomendacji (godzina 2:00 w nocy)
0 2 * * * /path/to/project/bin/run.sh ./bin/console app:recommendations:update --quiet --max-recommendations=500

# Alternatywnie: co 3 dni o 3:00
0 3 */3 * * /path/to/project/bin/run.sh ./bin/console app:recommendations:update --quiet --max-recommendations=200

# Aktualizacja wektorów książek (po dodaniu nowych książek)
30 1 * * * /path/to/project/bin/run.sh ./bin/console app:process:ebook-embeddings --quiet --max-books=1000
```

### Wyjaśnienie parametrów:

- `--quiet`: Brak wyjścia konsoli (cron-friendly)
- `--max-recommendations`: Limit przetwarzanych rekomendacji (zabezpieczenie przed długim czasem wykonania)
- `*/3`: Co 3 dni

### Monitorowanie:

Po skonfigurowaniu crona sprawdź logi:
```bash
# Sprawdź status ostatniego wykonania
crontab -l
tail -f /var/log/cron.log  # lub odpowiedni plik logów

# Ręczne testowanie
./bin/run.sh ./bin/console app:recommendations:update --max-recommendations=10
```

### Przywracanie po awarii:

Jeśli cron nie działał przez dłuższy czas:
```bash
# Pełna aktualizacja wszystkich rekomendacji
./bin/run.sh ./bin/console app:recommendations:search-books --force --no-interaction
```
