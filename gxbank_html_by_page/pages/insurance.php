<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT * FROM insurance_policies WHERE user_id = ? ORDER BY policy_id DESC");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$policies = mysqli_stmt_get_result($stmt);

$stmt = mysqli_prepare($conn, "SELECT 
    COALESCE(SUM(CASE WHEN policy_status = 'active' THEN coverage_amount ELSE 0 END), 0) AS total_coverage,
    COALESCE(SUM(CASE WHEN policy_status = 'active' THEN monthly_premium ELSE 0 END), 0) AS total_premium,
    COUNT(*) AS total_policy
    FROM insurance_policies WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>GX Protect</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* { font-family: 'DM Sans', sans-serif; }
body { box-sizing: border-box; }
.policy-card { transition: transform .15s, border-color .15s, box-shadow .15s; }
.policy-card:hover { transform: translateY(-3px); border-color: rgba(14,165,233,.65); box-shadow: 0 12px 28px rgba(0,0,0,.25); }
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
                <h1 class="text-2xl font-bold text-white mb-1">GX Protect</h1>
                <p class="text-slate-400 text-sm">Insurance powered by fuzzy risk logic.</p>
            </div>

            <a href="/gxbank_html_by_page/pages/apply-insurance.php"
               class="w-11 h-11 rounded-xl bg-sky-500 hover:bg-sky-600 flex items-center justify-center transition">
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

        <div class="bg-gradient-to-br from-sky-500 to-sky-700 rounded-2xl p-6 mb-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>

            <div class="relative z-10">
                <p class="text-sky-100 text-sm font-medium">Total Active Coverage</p>
                <p class="text-white text-3xl font-bold mt-2">
                    RM <?php echo number_format($summary['total_coverage'], 2); ?>
                </p>

                <div class="grid grid-cols-2 gap-3 mt-5 border-t border-white/20 pt-4">
                    <div>
                        <p class="text-sky-100 text-xs">Monthly Premium</p>
                        <p class="text-white font-semibold">
                            RM <?php echo number_format($summary['total_premium'], 2); ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-sky-100 text-xs">Policies</p>
                        <p class="text-white font-semibold">
                            <?php echo intval($summary['total_policy']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <a href="/gxbank_html_by_page/pages/apply-insurance.php"
           class="block w-full text-center bg-sky-500 hover:bg-sky-600 text-white font-semibold py-3 rounded-xl transition mb-4">
            + Apply Insurance
        </a>

        <?php if (mysqli_num_rows($policies) == 0) { ?>

            <div class="bg-slate-900 rounded-2xl p-6 border border-slate-800 text-center">
                <div class="w-16 h-16 rounded-2xl bg-sky-500/15 flex items-center justify-center mx-auto mb-5">
                    <i data-lucide="shield-check" class="w-8 h-8 text-sky-400"></i>
                </div>

                <h2 class="text-white text-xl font-bold mb-2">No Insurance Yet</h2>
                <p class="text-slate-400 text-sm">
                    Apply a plan and let fuzzy logic evaluate your risk profile.
                </p>
            </div>

        <?php } else { ?>

            <div class="space-y-4">
                <?php while($p = mysqli_fetch_assoc($policies)) { ?>
                    <a href="/gxbank_html_by_page/pages/insurance-detail.php?policy_id=<?php echo intval($p['policy_id']); ?>"
                       class="policy-card group block bg-slate-900 rounded-2xl p-5 border border-slate-800">

                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="w-11 h-11 rounded-xl bg-sky-500/15 flex items-center justify-center mb-4">
                                    <i data-lucide="shield" class="w-5 h-5 text-sky-400"></i>
                                </div>

                                <p class="text-white font-bold text-lg">
                                    <?php echo htmlspecialchars($p['plan_name']); ?>
                                </p>

                                <p class="text-slate-500 text-xs mt-1">
                                    Risk: <?php echo htmlspecialchars($p['fuzzy_risk_level']); ?> · Score <?php echo number_format($p['fuzzy_risk_score'], 1); ?>
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-slate-400 text-xs">Coverage</p>
                                <p class="text-white font-bold">
                                    RM <?php echo number_format($p['coverage_amount'], 2); ?>
                                </p>

                                <p class="<?php echo $p['policy_status'] == 'active' ? 'text-green-400' : 'text-red-400'; ?> text-xs mt-2">
                                    <?php echo strtoupper($p['policy_status']); ?>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-slate-800 flex justify-between items-center">
                            <div>
                                <p class="text-slate-500 text-xs">Monthly Premium</p>
                                <p class="text-slate-300 text-sm font-medium">
                                    RM <?php echo number_format($p['monthly_premium'], 2); ?>
                                </p>
                            </div>

                            <div class="flex items-center gap-1 text-sky-400 text-xs opacity-80 group-hover:opacity-100">
                                <span>Open</span>
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </div>
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