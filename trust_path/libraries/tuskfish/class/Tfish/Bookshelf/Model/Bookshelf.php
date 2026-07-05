<?php

declare(strict_types=1);

namespace Tfish\Bookshelf\Model;

/**
 * \Tfish\Bookshelf\Model\Bookshelf class file.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     Bookshelf
 */

/**
 * Model for the Bookshelf module.
 *
 * The bookshelf is a hand-curated grid of book covers, grouped under subject headings. It is NOT
 * backed by the Tuskfish content database: some titles are external (a cover image plus an off-site
 * link), so the list cannot be generated from a DB query and is maintained here by hand.
 *
 * Data shape returned by sections():
 *
 *   [
 *     [
 *       'subject' => 'Marine biology',            // h2 heading for the section
 *       'books' => [
 *         [
 *           'title' => 'The Log from the Sea of Cortez', // used for alt text / placeholder label
 *           'cover' => '/uploads/image/cortez.jpg', // path relative to web root, or '' for a placeholder
 *           'url'   => 'https://example.com/book',  // where the cover links to (internal or external)
 *         ],
 *         // ...
 *       ],
 *     ],
 *     // ...
 *   ]
 *
 * To add a book, drop its cover into uploads/image/ and add a row (the resizer builds the responsive
 * srcset). Leave 'cover' empty to render a labelled placeholder (useful while you gather artwork).
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     Bookshelf
 */
class Bookshelf
{
    /** Constructor. */
    public function __construct() {}

    /**
     * Return the curated bookshelf, grouped by subject.
     *
     * @return array Ordered list of sections, each with a 'subject' and a list of 'books'.
     */
    public function sections(): array
    {
        return [
            [
                'subject' => 'Artemia',
                'books' => [
                    ['title' => 'Manual on Artemia production and use', 'cover' => '/uploads/image/fao-manual-on-artemia-production-and-use.jpg', 'url' => 'https://enaca.org/?id=1335'],
                ],
            ],
            [
                'subject' => 'Culture-based fisheries',
                'books' => [
                    ['title' => 'Better-practice approaches for culture-based fisheries development in Asia', 'cover' => '/uploads/image/cbf_manual.jpg', 'url' => 'https://enaca.org/?id=452'],
                    ['title' => 'Perspectives on culture-based fisheries developments in Asia', 'cover' => '/uploads/image/perspectives-on-culture-based-fisheries-developments-in-asia.jpg', 'url' => 'https://enaca.org/?id=280'],
                    ['title' => 'Status of Reservoir Fisheries in Five Asian Countries', 'cover' => '/uploads/image/status_reservoir_fisheries_asia.jpg', 'url' => 'https://enaca.org/?id=299'],
                    ['title' => 'Culture-based fisheries in Sri Lankan reservoirs: from science to practice', 'cover' => '/uploads/image/culture-based-fisheries-sri-lankan-reservoirs.jpg', 'url' => 'https://www.aciar.gov.au/publication/PR151-culture-based-fisheries'],
                ],
            ],
            [
                'subject' => 'Freshwater fish',
                'books' => [
                    ['title' => 'Better Management Practices for Striped (Tra) Catfish Farming in the Mekong Delta, Vietnam', 'cover' => '/uploads/image/catfish-bmp-vietnam.jpg', 'url' => 'https://enaca.org/?id=447'],
                    ['title' => 'Tilapia culture: The basics', 'cover' => '/uploads/image/tilapia-culture-basics.jpg', 'url' => 'https://repository.seafdec.org/handle/20.500.12066/6456'],
                    ['title' => 'The Grass Carp Aquaculture Manual', 'cover' => '/uploads/image/fai-grass-carp-aquaculture-manual.jpg', 'url' => 'https://enaca.org/?id=1338'],
                    ['title' => 'Guidelines for hatchery production of Pa Phia fingerlings in Lao PDR', 'cover' => '/uploads/image/labeo-chrysophekadion-hatchery-production.png', 'url' => 'https://enaca.org/?id=446'],
                    ['title' => 'Nursery culture of tropical anguillid eels in the Philippines', 'cover' => '/uploads/image/nursery-culture-tropical-anguillid-eels-philippines.jpg', 'url' => 'https://repository.seafdec.org.ph/handle/10862/3444'],
                    ['title' => 'Assessment of freshwater fish seed resources for sustainable aquaculture', 'cover' => '/uploads/image/assessment-freshwater-fish-seed-resources-sustainable-aquaculture.jpg', 'url' => 'https://openknowledge.fao.org/server/api/core/bitstreams/2ccb43a9-cd96-4510-b29c-1f79ed4356e9/content'],
                    ['title' => 'Sturgeon Hatchery Manual', 'cover' => '/uploads/image/sturgeon-hatchery-manual.jpg', 'url' => 'https://www.fao.org/4/i2144e/i2144e.pdf'],
                ],
            ],
            [
                'subject' => 'Freshwater prawns',
                'books' => [
                    ['title' => 'Breeding and seed production of the giant freshwater prawn (Macrobrachium rosenbergii)', 'cover' => '/uploads/image/seafdec-macrobrachium-breeding-seed-production.jpg', 'url' => 'https://repository.seafdec.org.ph/handle/10862/2418'],
                    ['title' => 'Farming freshwater prawns: A manual for the culture of the giant river prawn (Macrobrachium rosenbergii)', 'cover' => '/uploads/image/macrobrachium-manual-2003.jpg', 'url' => 'https://library.enaca.org/Shrimp/Publications/FAO_Macrobrachium_manual_2003.pdf'],
                ],
            ],
            [
                'subject' => 'Genetics and biodiversity',
                'books' => [
                    ['title' => 'Genetic management of Indian major carps', 'cover' => '/uploads/image/genetic-management-of-indian-major-carps.jpg', 'url' => 'https://enaca.org/?id=1296'],
                    ['title' => 'Guidelines for broodstock management, propagation and culture of semah, Tor douronensis and empurau, Tor tambroides', 'cover' => '/uploads/image/broodstock-guidelines-08.png', 'url' => 'https://enaca.org/?id=296'],
                    ['title' => 'Guidelines for genetic management and conservation of empurau, Tor tambroides and semah, Tor douronensis', 'cover' => '/uploads/image/genetic-management-and-conservation-of-mahseer.png', 'url' => 'https://enaca.org/?id=582'],
                ],
            ],
            [
                'subject' => 'Marine fish',
                'books' => [
                    ['title' => 'Nursery management of grouper: A best-practice manual', 'cover' => '/uploads/image/nursery_management_of_grouper_best_practices.jpg', 'url' => 'https://enaca.org/?id=476'],
                    ['title' => 'Hatchery management of tiger grouper (Epinephelus fuscoguttatus): A best-practice manual', 'cover' => '/uploads/image/hatchery_management_of_tiger_grouper.jpg', 'url' => 'https://enaca.org/?id=475'],
                    ['title' => 'Nursery and grow-out culture of snubnose pompano (Trachinotus blochii, Lacepede) in brackishwater ponds', 'cover' => '/uploads/image/nursery-growout-snubnose-pompano-brackishwater-ponds.jpg', 'url' => 'https://repository.seafdec.org.ph/handle/10862/6447'],
                    ['title' => 'Nursery and grow-out culture of rabbitfish Siganus guttatus in brackishwater ponds', 'cover' => '/uploads/image/nursery-growout-rabbitfish-siganus-guttatus-brackishwater.jpg', 'url' => 'https://repository.seafdec.org.ph/handle/10862/6396'],
                    ['title' => 'Hatchery, nursery and grow-out techniques for the flathead grey mullet (Mugil cephalus)', 'cover' => '/uploads/image/fao-grey-mullet-manual.jpg', 'url' => ' https://enaca.org/?id=1494'],
                ],
            ],
            [
                'subject' => 'Molluscs and shellfish',
                'books' => [
                    ['title' => 'Pacific oyster farming: A practical manual', 'cover' => '/uploads/image/pacific-oyster-farming-practical-manual.jpg', 'url' => 'https://enaca.org/?id=1336'],
                    ['title' => 'The giant clam: an ocean culture manual', 'cover' => '/uploads/image/giant-clam-ocean-culture-manual.jpg', 'url' => 'https://www.aciar.gov.au/publication/books-and-manuals/giant-clam-ocean-culture-manual'],
                    ['title' => 'The giant clam: a hatchery and nursery culture manual', 'cover' => '/uploads/image/giant-clam-hatchery-and-nursery-culture-manual.jpg', 'url' => 'https://www.aciar.gov.au/publication/books-and-manuals/giant-clam-hatchery-and-nursery-culture-manual'],
                    ['title' => 'The giant clam: an anatomical and histological atlas', 'cover' => '/uploads/image/giant-clam-anatomical-histological-atlas.jpg', 'url' => 'https://www.aciar.gov.au/publication/books-and-manuals/giant-clam-anatomical-and-histological-atlas'],
                    ['title' => 'Biology and Management of Invasive Apple Snails', 'cover' => '/uploads/image/biology-and-management-of-apple-snails.jpg', 'url' => 'https://enaca.org/?id=931'],
                ],
            ],
            [
                'subject' => 'Mud crabs',
                'books' => [
                    ['title' => 'Mud crab aquaculture: A practical manual', 'cover' => '/uploads/image/mud-crab-aquaculture-practical-manual.jpg', 'url' => 'https://www.fao.org/4/ba0110e/ba0110e.pdf'],
                    ['title' => 'Nursery culture of mangrove crab megalopae (Scylla serrata) in pond-based net cages', 'cover' => '/uploads/image/nursery-culture-mangrove-crab-megalopae-scylla-serrata.jpg', 'url' => 'https://repository.seafdec.org.ph/handle/10862/6607'],
                    ['title' => 'Biology and Hatchery of Mud Crabs (Scylla spp.)', 'cover' => '/uploads/image/biology-and-hatchery-mud-crabs-scylla-spp.jpg', 'url' => 'https://repository.seafdec.org.ph/bitstream/handle/10862/1986/aem34.pdf'],
                ],
            ],
            [
                'subject' => 'Sea cucumbers',
                'books' => [
                    ['title' => 'Hatchery production of sea cucumbers (sandfish Holothuria scabra)', 'cover' => '/uploads/image/hatchery-production-of-sea-cucumbers.png', 'url' => 'https://repository.seafdec.org.ph/handle/10862/6336'],
                    ['title' => 'Hatchery Manual for Sea Cucumber Aquaculture in the U.S. Affiliated Pacific Islands', 'cover' => '/uploads/image/hatchery-manual-for-sea-cucumber-aquaculture.jpg', 'url' => 'https://www.ctsa.org/files/publications/SeaCucumberHatcheryManual.pdf'],
                ],
            ],
            [
                'subject' => 'Seaweeds, plants and aquaponics',
                'books' => [
                    ['title' => 'Global Seaweed: New and Emerging Markets Report 2023', 'cover' => '/uploads/image/global-seaweed-new-emerging-markets-report.jpg', 'url' => 'https://enaca.org/?id=1372'],
                    ['title' => 'Better management practices for seaweed farming (Eucheuma and Kappaphycus)', 'cover' => '/uploads/image/seaweed-culture-bmp-manual.jpg', 'url' => 'https://enaca.org/?id=474'],
                    ['title' => 'Small-scale aquaponic food production: Integrated fish and plant farming', 'cover' => '/uploads/image/small-scale-aquaponic-food-production.jpg', 'url' => 'https://openknowledge.fao.org/handle/20.500.14283/i4021e'],
                ],
            ],
            [
                'subject' => 'Shrimp',
                'books' => [
                    ['title' => 'Shrimp Health Management Extension Manual', 'cover' => '/uploads/image/shrimp-health-management-extension-manual.jpg', 'url' => 'https://enaca.org/?id=292'],
                    ['title' => 'Mixed shrimp-mangrove farming practices: A manual for farmers', 'cover' => '/uploads/image/mixed-shrimp-mangrove-farming-practices-manual.png', 'url' => 'https://enaca.org/?id=548'],
                    ['title' => 'Mixed shrimp-mangrove farming practices: A manual for extension workers', 'cover' => '/uploads/image/mixed-shrimp-mangrove-farming-practices-manual-for-extension-workers.png', 'url' => 'https://enaca.org/?id=549'],
                ],
            ],
            [
                'subject' => 'Cluster-based approaches to small-scale farming',
                'books' => [
                    ['title' => 'Self-use manual on group formation and group certification of small-scale aqua-farmers', 'cover' => '/uploads/image/small-scale-farmer-group-formation-certification.jpg', 'url' => 'https://enaca.org/?id=577'],
                    ['title' => 'Guide to establishment of community-based aquaculture management groups', 'cover' => '/uploads/image/vietnam-bmp-cluster-formation.jpg', 'url' => 'https://enaca.org/?id=578'],
                ],
            ],
            [
                'subject' => 'Health and biosecurity',
                'books' => [
                    ['title' => 'Asia Diagnostic Guide to Aquatic Animal Diseases', 'cover' => '/uploads/image/asia-diagnostic-guide-to-aquatic-animal-diseases.jpg', 'url' => 'https://enaca.org/?id=707'],
                    ['title' => 'Tilapia Lake Virus Disease Strategy Manual', 'cover' => '/uploads/image/tilapia-lake-virus-disease-strategy-manual.jpg', 'url' => 'https://doi.org/10.4060/cb7293en'],
                ],
            ],
            [
                'subject' => 'Nutrition and feeding',
                'books' => [
                    ['title' => 'On-farm feeding and feed management in aquaculture', 'cover' => '/uploads/image/on-farm-feeding-and-feed-management-in-aquaculture.jpg', 'url' => 'https://www.fao.org/4/i3481e/i3481e.pdf'],
                    ['title' => 'Manual on the Production and Use of Live Food for Aquaculture', 'cover' => '/uploads/image/manual-production-use-live-food-for-aquaculture.jpg', 'url' => 'https://openknowledge.fao.org/bitstreams/63ed2a4b-cd62-4ce4-ae99-6700aa0ec0aa/download'],
                ],
            ],
            [
                'subject' => 'Statistics and trends',
                'books' => [
                    ['title' => 'The State of World Fisheries and Aquaculture 2026', 'cover' => '/uploads/image/state-world-fisheries-aquaculture-2026.jpg', 'url' => 'https://enaca.org/?id=1496'],
                    ['title' => 'The State of Food Security and Nutrition in the World 2025', 'cover' => '/uploads/image/state-food-security-nutrition-in-world.jpg', 'url' => 'https://openknowledge.fao.org/items/4b1f7d26-267d-4a81-aed4-4f9de4d93f85'],
                    ['title' => 'The State of the World\'s Land and Water Resources for Food and Agriculture', 'cover' => '/uploads/image/state-worlds-land-water-agriculture.jpg', 'url' => 'https://openknowledge.fao.org/items/feba76d0-dc7e-4ad3-b287-39426f3822fd'],
                    ['title' => 'GLOBEFISH Highlights: International markets for fisheries and aquaculture products 2026', 'cover' => '/uploads/image/globefish-highlights-q1-2026.jpg', 'url' => 'https://openknowledge.fao.org/items/b601997c-ead5-48e1-bcec-c4e631b199e2'],
                ],
            ],
            [
                'subject' => 'Miscellaneous',
                'books' => [
                    ['title' => 'Success Stories in Asian Aquaculture', 'cover' => '/uploads/image/success-stories-asian-aquaculture.jpg', 'url' => 'https://enaca.org/?id=303'],
                    ['title' => 'Climate change implications for fisheries and aquaculture: Overview of current scientific knowledge', 'cover' => '/uploads/image/fao-tech-paper-climate-change-2009.jpg', 'url' => 'https://enaca.org/?id=442'],
                ],
            ],
        ];
    }
}
