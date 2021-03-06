<?php require __DIR__ . '/vendor/autoload.php';

use App\Search\Types\SearchBankAccounts;
use App\Search\Highlight\Highlight;
use App\Search\SearchMultiQuery;
use Elasticsearch\ClientBuilder;

$search = (isset($_GET['search'])) ? $_GET['search'] : '';
$balance_from = (isset($_GET['balance_from'])) ? $_GET['balance_from'] : '';
$balance_to = (isset($_GET['balance_to'])) ? $_GET['balance_to'] : '';
$city = (isset($_GET['city'])) ? $_GET['city'] : '';
$state = (isset($_GET['state'])) ? $_GET['state'] : '';
$date_from = (!empty($_GET['date_from'])) ? $_GET['date_from'] : 1;
$date_to = (!empty($_GET['date_to'])) ? $_GET['date_to'] : 1;
$from = (isset($_GET['from'])) ? $_GET['from'] : 0;
$size = (isset($_GET['size'])) ? $_GET['size'] : 10;

$from_age = date_diff(date_create($date_from), date_create('today'))->y;
$to_age = date_diff(date_create($date_to), date_create('today'))->y;

$searchable_fields = ['firstname', 'lastname'];
$searchQuery = new SearchMultiQuery($search, $balance_from, $balance_to, $city, $state, $from_age, $to_age, $searchable_fields);
$highlighter = new Highlight($searchable_fields);

$client = ClientBuilder::create()->build();
$searchBankAccounts = new SearchBankAccounts($client, $searchQuery);
$searchBankAccounts->setHighlighter($highlighter);
$searchBankAccounts->setFrom($from);
$searchBankAccounts->setSize($size);


// Descomenta estas líneas si quieres ver la query que se está ejecutando.
// Útil en el caso de que haya un error de sintaxis
echo "<pre>";
echo json_encode($searchBankAccounts->getQuery(), JSON_PRETTY_PRINT);
echo "</pre>";

$filterResults = $searchBankAccounts->search();

function getHighlight($result, $field)
{
    if (isset($result['highlight'][$field][0])) {
        return $result['highlight'][$field][0];
    }
    return $result['_source'][$field];
}

?>

<style>
    .table-results em {
        background-color: #FFFF00;
        font-weight: bold;
    }
</style>

<div class="box">
    <div class="box-header">
        <h3 class="box-title">Results (hits=<?php echo $total_hits = $filterResults['hits']['total'] ?>)</h3>

        <div class="box-tools">
            <div class="input-group input-group-sm" style="width: 150px;">
            </div>
        </div>
    </div>
    <div class="box-body table-responsive">
        <table class="table table-hover table-striped table-results">
            <tbody>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Surname</th>
                <th>Age</th>
                <th>Balance</th>
                <th>Email</th>
                <th>City</th>
                <th>State</th>
                <th>ElasticSearch sort score</th>
            </tr>
            <?php
            foreach ($filterResults['hits']['hits'] as $filterResult) {
                $result = $filterResult['_source'];
                ?>
                <tr>
                    <td>#<?php echo $result['account_number'] ?></td>
                    <td><?php echo getHighlight($filterResult, 'firstname') ?></td>
                    <td><?php echo getHighlight($filterResult, 'lastname') ?></td>
                    <td><?php echo $result['age'] ?></td>
                    <td>$ <?php echo $result['balance'] ?></td>
                    <td><?php echo $result['email'] ?></td>
                    <td><?php echo $result['city'] ?></td>
                    <td><?php echo $result['state'] ?></td>
                    <td><?php echo $filterResult['_score'] ?></td>
                </tr>
                <?php
            }
            ?>
            <tr>
                <td colspan="6">
                    <?php $n_pages = $total_hits / $size; ?>
                    <?php if($from > 0) { ?>
                        <a href="/?from=<?php echo $from - $size; ?>">Prev.</a>
                    <?php }
                    for ($i = 1; $i < $n_pages; $i++) { ?>
                        <a href="/?from=<?php echo $i * $size; ?>"><?php echo $i; ?></a>
                    <?php }
                    if ($from < $total_hits - $size) { ?>
                        - <a href="/?from=<?php echo $from + $size; ?>">Next</a>
                    <?php } ?>
                </td>
            </tr>
            </tbody>
        </table>
        <hr>
        <?php
        echo "<pre>ElasticSearch QUERY \n";
        echo json_encode($searchBankAccounts->getQuery(), JSON_PRETTY_PRINT);
        echo "</pre>";
        echo "<pre>ElasticSearch Response \n";
        echo json_encode($filterResults, JSON_PRETTY_PRINT);
        echo "</pre>";
        ?>
    </div>
</div>
