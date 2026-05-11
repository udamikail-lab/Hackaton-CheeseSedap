<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pocket_name = trim($_POST['pocket_name'] ?? '');
    $target_amount = floatval($_POST['target_amount'] ?? 0);
    $deadline = $_POST['deadline'] ?? null;

    if ($pocket_name == "" || $target_amount <= 0) {
        $error = "Please enter tabung name and valid target amount.";
    } else {
        if ($deadline == "") {
            $deadline = null;
        }

        $current_amount = 0.00;

        $stmt = mysqli_prepare($conn, "INSERT INTO bonus_pockets 
            (user_id, pocket_name, target_amount, current_amount, deadline)
            VALUES (?, ?, ?, ?, ?)");

        mysqli_stmt_bind_param($stmt, "isdds", $user_id, $pocket_name, $target_amount, $current_amount, $deadline);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Tabung created successfully.";
            header("Location: /gxbank_html_by_page/pages/bonus-pockets.php");
            exit();
        } else {
            $error = "Failed to create tabung: " . mysqli_error($conn);
        }
    }
}
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Add Tabung</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* { font-family: 'DM Sans', sans-serif; }
body { box-sizing: border-box; }
</style>
</head>

<body class="h-full">
<div class="h-full w-full bg-slate-950 overflow-auto">
    <div class="max-w-md mx-auto px-5 py-8">

        <a href="/gxbank_html_by_page/pages/bonus-pockets.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Back</span>
        </a>

        <h1 class="text-2xl font-bold text-white mb-2">Add Tabung</h1>
        <p class="text-slate-400 text-sm mb-6">Create a new savings pocket.</p>

        <?php if($error != "") { ?>
            <div class="bg-red-500/15 text-red-300 rounded-xl p-3 text-sm mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php } ?>

        <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800">
            <form method="POST" class="space-y-4">

                <div>
                    <label class="text-slate-300 text-sm font-medium">Tabung Name</label>
                    <input type="text" name="pocket_name" required
                        placeholder="Example: Emergency Fund"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Target Amount</label>
                    <input type="number" step="0.01" name="target_amount" required
                        placeholder="Example: 3000"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Deadline</label>
                    <input type="date" name="deadline"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-400">
                </div>

                <button type="submit"
                    class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 rounded-xl transition">
                    Create Tabung
                </button>

            </form>
        </div>

    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>