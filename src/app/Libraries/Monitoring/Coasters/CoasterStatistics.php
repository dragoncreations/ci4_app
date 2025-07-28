<?php

namespace App\Libraries\Monitoring\Coasters;

use DateTime;

class CoasterStatistics
{
    private string $id;

    /**
     * Nazwa kolejki
     * @var string
     */
    private string $name;

    /**
     * Godziny działania
     * @var string
     * Format: "H:i - H:i"
     * Example: "10:00 - 18:00"
     */
    private string $hours;

    /**
     * Liczba wagonów dostępnych
     * @var int
     */
    private int $availableWagons = 0;

    /**
     * Liczba wagonów wymaganych
     * @var int
     */
    private int $requiredWagons = 0;

    /**
     * Liczba personelu dostępnego
     * @var int
     */
    private int $availableStaff = 0;

    /**
     * Liczba personelu wymaganego
     * @var int
     */
    private int $requiredStaff = 0;

    /**
     * Liczba klientów dziennie
     * @var int
     */
    private int $customers;

    /**
     * Długość trasy (w metrach)
     * @var int
     */
    private int $distance;

    /**
     * Godzina rozpoczęcia pracy kolejki
     * @var DateTime
     */
    private DateTime $timeFrom;

    /**
     * Godzina zakończenia pracy kolejki
     * @var DateTime
     */
    private DateTime $timeTo;

    /**
     * Lista wagonów
     * @var array
     */
    private array $wagons = [];

    /**
     * Lista wykrytych problemów
     * @var array
     */
    private array $problems = [];

    /**
     * Lista informacji
     * @var array
     */
    private array $info = [];

    public function __construct(array $data)
    {
        $this->id = $data[1];
        $this->name = "Kolejka A" . $data[1];
        $this->hours = new DateTime($data[9])->format('H:i') . " - " . new DateTime($data[11])->format('H:i');
        $this->customers = (int)$data[5];
        $this->availableStaff = (int)$data[3];
        $this->distance = (int)$data[7];
        $this->timeFrom = new DateTime($data[9]);
        $this->timeTo = new DateTime($data[11]);
        $this->setWagons($data);
        $this->availableWagons = count($this->wagons);
        $this->setRequiredWagons();
        $this->setRequiredStaff();

        if (!empty($this->problems)) {
            $this->log($this->name . " - Problem: " . implode(", ", $this->problems));
        }
    }

    public function display(): void
    {
        echo "[{$this->name}]" . PHP_EOL .
            "Godziny działania: {$this->hours}" . PHP_EOL .
            "Liczba wagonów: {$this->availableWagons}/{$this->requiredWagons}" . PHP_EOL .
            "Dostępny personel: {$this->availableStaff}/{$this->requiredStaff}" . PHP_EOL .
            "Klienci dziennie: {$this->customers}" . PHP_EOL .
            (!empty($this->info) ? implode(", ", $this->info) : "") . PHP_EOL .
            (!empty($this->problems) ? "Problem: " . implode(", ", $this->problems) : "Status: OK") . PHP_EOL . PHP_EOL;
    }

    private function setWagons(array $data): void
    {
        $matches  = preg_grep('/^wagon_(\d+)/i', $data);

        if (!empty($matches)) {
            foreach (array_keys($matches) as $idx) {
                $this->wagons[] = json_decode($data[$idx + 1], true);
            }
        }
    }

    private function setRequiredWagons(): void
    {
        // Wyliczenie liczby wymaganych wagonów ma sens jedynie w przypadku, 
        // gdy wszystkie wagony określonej kolejki mają tę samą pojemność 
        // i poruszają się z tą samą prędkością.
        // W przeciwnym razie powyższe zadanie nie ma sensu.

        // Ponieważ zakładamy, że wszystkie wagony mają tę samą pojemność i prędkość,
        // do obliczeń możemy użyć danych dowolnego wagonu np. pierwszego.

        if (!empty($this->wagons)) {
            $wagon = $this->wagons[0];

            $duration = (int)ceil($this->distance / floatval($wagon['speed'])); // czas przejazdu wagonu w jedną stronę (w sekundach)

            $duration += 5 * 60; // dodanie 5 minut np. na załadunek/rozładunek

            $duration *= 2; // czas przejazdu w obie strony

            $workingTime = $this->timeTo->getTimestamp() - $this->timeFrom->getTimestamp(); // czas pracy kolejki (w sekundach)

            $customersPerWagonByDay = ($wagon['capacity'] * $workingTime) / $duration; // liczba klientów przewiezionych w obie strony w pojedynczym wagonie w ciągu dnia

            $this->requiredWagons = (int)ceil($this->customers / $customersPerWagonByDay); // liczba wymaganych wagonów

            if ($this->requiredWagons > $this->availableWagons) {
                $this->problems[] = "Brakuje " . $this->requiredWagons - $this->availableWagons . " wagonów";
            }

            if ($customersPerWagonByDay * $this->availableWagons > 2 * $this->customers) {
                $this->info[] = "Kolejka jest w stanie przewieźć ponad dwukrotnie więcej klientów niż zaplanowano";
            }
        } else {
            $this->problems[] = "Brak wagonów w kolejce";
        }
    }

    private function setRequiredStaff(): void
    {
        $this->requiredStaff = 1 + 2 * $this->requiredWagons; // 1 pracownik + 2 pracowników na każdy wagon

        if ($this->requiredStaff > $this->availableStaff) {
            $this->problems[] = "Brakuje " . $this->requiredStaff - $this->availableStaff . " pracowników";
        }
    }

    private function log(string $message): void
    {
        // Logika do zapisywania problemów lub informacji do pliku
        $file = '../writable/logs/coaster_statistics.log';

        $fp = fopen($file, 'a');

        fwrite($fp, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL);

        fclose($fp);
    }
}
