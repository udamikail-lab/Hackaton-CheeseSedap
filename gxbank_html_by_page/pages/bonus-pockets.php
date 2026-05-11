<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT * FROM bonus_pockets WHERE user_id = ? ORDER BY pocket_id DESC");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$pockets = mysqli_stmt_get_result($stmt);
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Bonus Pockets</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* { font-family: 'DM Sans', sans-serif; }
body { box-sizing: border-box; }
.pocket-card { transition: transform .15s, border-color .15s, box-shadow .15s; }
.pocket-card:hover { transform: translateY(-3px); border-color: rgba(16,185,129,.65); box-shadow: 0 12px 28px rgba(0,0,0,.25); }
</style>
</head>

<body class="h-full">
<div class="h-full w-full bg-slate-950 overflow-auto">
    <div class="max-w-md mx-auto px-5 py-8">

        <a href="/gxbank_html_by_page/dashboard.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Back</span>
        </a>

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white mb-1">Bonus Pockets</h1>
                <p class="text-slate-400 text-sm">Manage your tabung from main account.</p>
            </div>

            <a href="/gxbank_html_by_page/pages/add-pocket.php"
               class="w-11 h-11 rounded-xl bg-emerald-500 hover:bg-emerald-600 flex items-center justify-center transition">
                <i data-lucide="plus" class="w-5 h-5 text-white"></i>
            </a>
        </div>

        <?php if(isset($_SESSION['success'])) { ?>
            <div class="bg-emerald-500/15 text-emerald-300 rounded-xl p-3 text-sm mb-4">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php } ?>

        <?php if(isset($_SESSION['error'])) { ?>
            <div class="bg-red-500/15 text-red-300 rounded-xl p-3 text-sm mb-4">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php } ?>

        <?php if (mysqli_num_rows($pockets) == 0) { ?>

            <div class="bg-slate-900 rounded-2xl p-6 border border-slate-800 text-center">
                <div class="w-16 h-16 rounded-2xl bg-emerald-500/15 flex items-center justify-center mx-auto mb-5">
                    <i data-lucide="piggy-bank" class="w-8 h-8 text-emerald-400"></i>
                </div>

                <h2 class="text-white text-xl font-bold mb-2">No Tabung Yet</h2>

                <p class="text-slate-400 text-sm mb-6">
                    Create a pocket and move money from your main account into it.
                </p>

                <a href="/gxbank_html_by_page/pages/add-pocket.php"
                   class="block w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 rounded-xl transition">
                    Create Tabung
                </a>
            </div>

        <?php } else { ?>

            <a href="/gxbank_html_by_page/pages/add-pocket.php"
               class="block w-full text-center bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 rounded-xl transition mb-4">
                + Add Tabung
            </a>

            <div class="space-y-4">
                <?php while($p = mysqli_fetch_assoc($pockets)) { 
                    $target = floatval($p['target_amount']);
                    $current = floatval($p['current_amount']);
                    $percent = $target > 0 ? min(100, ($current / $target) * 100) : 0;
                ?>
                    <a href="/gxbank_html_by_page/pages/pocket-detail.php?pocket_id=<?php echo intval($p['pocket_id']); ?>"
                       class="pocket-card group block bg-slate-900 rounded-2xl p-5 border border-slate-800">

                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="w-11 h-11 rounded-xl bg-emerald-500/15 flex items-center justify-center mb-4">
                                    <i data-lucide="piggy-bank" class="w-5 h-5 text-emerald-400"></i>
                                </div>

                                <p class="text-white font-bold text-lg">
                                    <?php echo htmlspecialchars($p['pocket_name']); ?>
                                </p>

                                <p class="text-slate-500 text-xs mt-1">
                                    Deadline: <?php echo $p['deadline'] ? htmlspecialchars($p['deadline']) : 'No deadline'; ?>
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-slate-400 text-xs">Saved</p>
                                <p class="text-white font-bold">
                                    RM <?php echo number_format($current, 2); ?>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="flex justify-between text-xs mb-2">
                                <span class="text-slate-400">Target RM <?php echo number_format($target, 2); ?></span>
                                <span class="text-emerald-400"><?php echo number_format($percent, 0); ?>%</span>
                            </div>

                            <div class="w-full bg-slate-700 rounded-full h-2">
                                <div class="bg-emerald-500 h-2 rounded-full" style="width: <?php echo $percent; ?>%"></div>
                            </div>
                        </div>

                        <div class="hidden group-hover:block mt-4 bg-slate-950 rounded-xl p-3 border border-slate-800">
                            <p class="text-slate-400 text-xs">
                                Click to transfer in/out, view details, or delete this tabung.
                            </p>
                        </div>

                    </a>
                <?php } ?>
            </div>

        <?php } ?>

    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>