<?php

namespace Database\Seeders;

use App\Models\ZonalOffice;
use Illuminate\Database\Seeder;

class ZonalOfficeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->offices() as $index => $office) {
            ZonalOffice::updateOrCreate(
                ['name' => $office['name']],
                [
                    'office_location' => $office['office_location'],
                    'districts_covered' => $office['districts_covered'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    private function offices(): array
    {
        return [
            ['name' => 'KCCA', 'office_location' => 'City Hall, Wing B, Ground Floor, Kimathi Avenue, Kampala City Council', 'districts_covered' => 'Kampala'],
            ['name' => 'Jinja', 'office_location' => 'Plot 68-72 Nile Crescent, next to UNRA', 'districts_covered' => 'Bugiri, Buyende, Iganga, Jinja, Kaliro, Kamuli, Luuka, Mayuge, Namayingo, Namutumba'],
            ['name' => 'Mukono', 'office_location' => 'Plot 17-21, Kaloli Matovu Road, Mukono Town', 'districts_covered' => 'Kayunga, Buikwe, Buvuma, Mukono'],
            ['name' => 'Wakiso (Kyadondo)', 'office_location' => 'Plot 190, Busiro Block 274, Wakiso. Next to District HQ', 'districts_covered' => 'Kyadondo in Wakiso'],
            ['name' => 'Wakiso (Busiro)', 'office_location' => 'Next to the District Police Office', 'districts_covered' => 'Busiro in Wakiso'],
            ['name' => 'Masaka', 'office_location' => "Plot 16 Broadway Masaka Road, next to Magistrate's Court", 'districts_covered' => 'Bukomansimbi, Kalangala, Kalungu, Lwengo, Lyantonde, Masaka, Rakai, Kyotera, Ssembabule'],
            ['name' => 'Mbarara', 'office_location' => 'Plot 2-14, Kamukuzi Road, Kamukuzi Hill', 'districts_covered' => 'Buhweju, Bukanga, Bushenyi, Ibanda, Isingiro, Kiruhura, Mbarara, Mitooma, Ntungamo, Rubirizi, Sheema, Rwampara and Kazo'],
            ['name' => 'Lira', 'office_location' => 'Plot 2, Hotel Road Senior Quarters', 'districts_covered' => 'Apac, Dokolo, Oyam, Alebtong, Kole, Amolatar, Lira, Otuke and Kwania'],
            ['name' => 'Kabarole', 'office_location' => 'Plot 30-34, Ttibaitwa Road', 'districts_covered' => 'Kabarole, Kasese, Kyegegwa, Kamwenge, Bunyangabu, Kitagewenda, Kyenjojo, Bundibugyo and Ntoroko'],
            ['name' => 'Kibaale', 'office_location' => 'Plot 08 Block 232, Buyaga, Kibaale Town', 'districts_covered' => 'Kibaale, Kagadi and Kakumiro'],
            ['name' => 'Masindi', 'office_location' => 'Plot 22, Port Road, Masindi', 'districts_covered' => 'Hoima, Buliisa, Kiryandongo, Masindi and Kikuube'],
            ['name' => 'Mbale', 'office_location' => 'Plot 21-23A Lyadda Road, Mbale', 'districts_covered' => 'Sironko, Bulambuli, Manafwa, Namisindwa, Kapchorwa, Kween, Bukwo, Bududa and Mbale'],
            ['name' => 'Arua', 'office_location' => 'Plot 8-16 Pajulu Road', 'districts_covered' => 'Zombo, Adjumani, Moyo, Maracha, Koboko, Nebbi, Yumbe, Pakwach, Obongi, Arua, Terego and Madi-Okolo'],
            ['name' => 'Gulu', 'office_location' => 'Plot 4A Princess Road, Gulu', 'districts_covered' => 'Lamwo, Nwoya, Amuru, Kitgum, Pader, Agago, Gulu and Omoro'],
            ['name' => 'Tororo', 'office_location' => 'Plot 2-4 District Road', 'districts_covered' => 'Budaka, Pallisa, Kibuku, Butebo, Busia, Butaleja, Bugweri and Tororo'],
            ['name' => 'Luwero', 'office_location' => 'Bukalasa', 'districts_covered' => 'Nakaseke, Nakasongola and Luwero'],
            ['name' => 'Rukungiri', 'office_location' => 'Plot 10, Nyerere Road', 'districts_covered' => 'Rukungiri and Kanungu'],
            ['name' => 'Kabale', 'office_location' => 'Plot 30 Sullivan Road', 'districts_covered' => 'Kabale, Rubanda, Rukiga and Kisoro'],
            ['name' => 'Soroti', 'office_location' => 'Plot 26 Central Avenue', 'districts_covered' => 'Soroti, Bukedea, Kumi, Amuria, Kaberamaido, Katakwi, Ngora, Kalaki, Kapelebyong and Serere'],
            ['name' => 'Mpigi', 'office_location' => 'Plot 232, Block 90, Mawokota', 'districts_covered' => 'Butambala, Gomba and Mpigi'],
            ['name' => 'Mityana', 'office_location' => 'Plot 907, Singo Block 148, Mityana', 'districts_covered' => 'Mubende, Mityana, Kyankwanzi, Kiboga and Kassanda'],
            ['name' => 'Moroto', 'office_location' => 'Plot 28 Lorika Road', 'districts_covered' => 'Nabilatuk, Karenga, Kaabong, Nakapiripirit, Napak, Abim, Amudat, Kotido and Moroto'],
        ];
    }
}
