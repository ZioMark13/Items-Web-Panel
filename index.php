<?php
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception("Il file .env non è stato trovato in: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

$servername = getenv('DB_SERVER');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (isset($_POST['itemId'], $_POST['label'], $_POST['limit'], $_POST['usable'], $_POST['desc'])) {
        $itemId = $_POST['itemId'];
        $label = $_POST['label'];
        $limit = $_POST['limit'];
        $usable = $_POST['usable'];
        $desc = $_POST['desc'];
        $stmt = $conn->prepare("UPDATE items SET label = ?, `limit` = ?, usable = ?, `desc` = ? WHERE item = ?");
        $stmt->bind_param("ssiss", $label, $limit, $usable, $desc, $itemId);
        if ($stmt->execute()) {
            echo 'success';
        } else {
            echo 'error';
        }
    } else {
        echo 'error';
    }

    exit;
}

$sql = "SELECT item, label, `limit`, usable, `desc` FROM items";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VORP | Web Item List</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
</head>
<body>
<div class="container mt-5">
<h2>Lista Items</h2>
    <input class="form-control mb-4" id="tableSearch" type="text" placeholder="Cerca...">
    <table class="table" id="itemsTable">
        <thead>
            <tr>
                <th>Item</th>
                <th>Label</th>
                <th>Limite</th>
                <th>Usabile</th>
                <th>Descrizione</th>
                <th>Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr data-item-id="<?php echo $row["item"]; ?>">
                        <td><?php echo $row["item"]; ?></td>
                        <td><?php echo $row["label"]; ?></td>
                        <td><?php echo $row["limit"]; ?></td>
                        <td><?php echo $row["usable"] == 1 ? 'Si' : 'No'; ?></td>
                        <td><?php echo $row["desc"]; ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm editBtn">Modifica</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">Nessun dato trovato, controlla la tabella "items" in database</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Modifica Item</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <div class="form-group">
                        <label for="editLabel">Label:</label>
                        <input type="text" class="form-control" id="editLabel" name="label">
                    </div>
                    <div class="form-group">
                        <label for="editLimit">Limite:</label>
                        <input type="number" class="form-control" id="editLimit" name="limit">
                    </div>
                    <div class="form-group">
                        <label for="editUsable">Usabile:</label>
                        <select class="form-control" id="editUsable" name="usable">
                            <option value="1">Si</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editDesc">Descrizione:</label>
                        <textarea class="form-control" id="editDesc" name="desc"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Chiudi</button>
                <button type="button" class="btn btn-primary" id="saveChanges">Salva</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    $(".editBtn").click(function(){
        var row = $(this).closest("tr");
        var itemId = row.data("item-id");
        var label = row.find("td:eq(1)").text();
        var limit = row.find("td:eq(2)").text();
        var usable = row.find("td:eq(3)").text() === "Si" ? 1 : 0;
        var desc = row.find("td:eq(4)").text();

        $("#editLabel").val(label);
        $("#editLimit").val(limit);
        $("#editUsable").val(usable);
        $("#editDesc").val(desc);

        $("#editModal").data("item-id", itemId);
        $("#editModal").modal("show");
    });

    $("#saveChanges").click(function(){
        var label = $("#editLabel").val();
        var limit = $("#editLimit").val();
        var usable = $("#editUsable").val();
        var desc = $("#editDesc").val();
        var itemId = $("#editModal").data("item-id");

        $.ajax({
            url: window.location.href,
            method: "POST",
            data: {
                action: "update",
                itemId: itemId,
                label: label,
                limit: limit,
                usable: usable,
                desc: desc
            },
            success: function(response){
                location.reload();
            },
            error: function(xhr, status, error){
                console.log(xhr.responseText);
            }
        });

        $("#editModal").modal("hide");
    });
});

</script>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
$(document).ready(function(){
  $("#tableSearch").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#itemsTable tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});
</script>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
