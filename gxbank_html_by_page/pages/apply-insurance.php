<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}

include "../php/dbconn.php";

$user_id = $_SESSION['user_id'];
$error = "";

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

function clampValue($value, $min, $max) {
    return max($min, min($max, $value));
}

function triangularMembership($x, $a, $b, $c) {
    if ($x <= $a || $x >= $c) {
        return 0;
    }

    if ($x == $b) {
        return 1;
    }

    if ($x > $a && $x < $b) {
        return ($x - $a) / ($b - $a);
    }

    return ($c - $x) / ($c - $b);
}

function leftShoulderMembership($x, $a, $b) {
    if ($x <= $a) {
        return 1;
    }

    if ($x >= $b) {
        return 0;
    }

    return ($b - $x) / ($b - $a);
}

function rightShoulderMembership($x, $a, $b) {
    if ($x <= $a) {
        return 0;
    }

    if ($x >= $b) {
        return 1;
    }

    return ($x - $a) / ($b - $a);
}

function calculateHealthRiskValue($smoking_status, $existing_illness) {
    if ($smoking_status == "non_smoker" && $existing_illness == "no") {
        return 20;
    }

    if ($smoking_status == "smoker" && $existing_illness == "no") {
        return 55;
    }

    if ($smoking_status == "non_smoker" && $existing_illness == "yes") {
        return 65;
    }

    return 90;
}

function calculateFuzzyInsuranceRisk($age, $income, $coverage, $smoking_status, $existing_illness) {
    $health_value = calculateHealthRiskValue($smoking_status, $existing_illness);

    $age_young = leftShoulderMembership($age, 25, 40);
    $age_middle = triangularMembership($age, 30, 45, 60);
    $age_senior = rightShoulderMembership($age, 50, 70);

    $income_low = leftShoulderMembership($income, 1500, 3500);
    $income_medium = triangularMembership($income, 2500, 5000, 8000);
    $income_high = rightShoulderMembership($income, 6500, 12000);

    $coverage_low = leftShoulderMembership($coverage, 50000, 120000);
    $coverage_medium = triangularMembership($coverage, 100000, 200000, 350000);
    $coverage_high = rightShoulderMembership($coverage, 250000, 500000);

    $health_low = leftShoulderMembership($health_value, 20, 45);
    $health_medium = triangularMembership($health_value, 35, 60, 80);
    $health_high = rightShoulderMembership($health_value, 70, 95);

    $low_rules = [];
    $medium_rules = [];
    $high_rules = [];

    $low_rules[] = min($age_young, $health_low, max($income_medium, $income_high));
    $low_rules[] = min($coverage_low, $health_low);
    $low_rules[] = min($income_high, $health_low, max($age_young, $age_middle));

    $medium_rules[] = min($age_middle, $health_medium);
    $medium_rules[] = min($coverage_medium, $income_medium);
    $medium_rules[] = min($age_middle, $coverage_medium);
    $medium_rules[] = min($income_low, $coverage_low, $health_low);

    $high_rules[] = max($age_senior, $health_high);
    $high_rules[] = min($coverage_high, $income_low);
    $high_rules[] = min($coverage_high, $health_medium);
    $high_rules[] = min($age_senior, $coverage_medium);
    $high_rules[] = min($income_low, $health_medium);

    $low_strength = max($low_rules);
    $medium_strength = max($medium_rules);
    $high_strength = max($high_rules);

    $denominator = $low_strength + $medium_strength + $high_strength;

    if ($denominator == 0) {
        $risk_score = 70;
    } else {
        $risk_score = (($low_strength * 25) + ($medium_strength * 55) + ($high_strength * 85)) / $denominator;
    }

    $risk_score = clampValue($risk_score, 0, 100);

    $smoker_loading_rate = 0.00;
    $illness_loading_rate = 0.00;
    $fuzzy_loading_rate = 0.00;

    if ($smoking_status == "smoker") {
        $smoker_loading_rate = 25.00;
    }

    if ($existing_illness == "yes") {
        $illness_loading_rate = 35.00;
    }

    if ($risk_score < 40) {
        $risk_level = "Low Risk";
        $decision = "approved";
        $fuzzy_loading_rate = 0.00;
    } elseif ($risk_score < 70) {
        $risk_level = "Medium Risk";
        $decision = "approved_higher_premium";
        $fuzzy_loading_rate = 20.00;
    } else {
        $risk_level = "High Risk";
        $decision = "rejected";
        $smoker_loading_rate = 0.00;
        $illness_loading_rate = 0.00;
        $fuzzy_loading_rate = 0.00;
    }

    $total_loading_rate = $smoker_loading_rate + $illness_loading_rate + $fuzzy_loading_rate;
    $premium_multiplier = $decision == "rejected" ? 0 : (1 + ($total_loading_rate / 100));

    return [
        "risk_score" => round($risk_score, 2),
        "risk_level" => $risk_level,
        "decision" => $decision,
        "smoker_loading_rate" => $smoker_loading_rate,
        "illness_loading_rate" => $illness_loading_rate,
        "fuzzy_loading_rate" => $fuzzy_loading_rate,
        "total_loading_rate" => $total_loading_rate,
        "premium_multiplier" => $premium_multiplier,
        "membership" => [
            "age_young" => $age_young,
            "age_middle" => $age_middle,
            "age_senior" => $age_senior,
            "income_low" => $income_low,
            "income_medium" => $income_medium,
            "income_high" => $income_high,
            "coverage_low" => $coverage_low,
            "coverage_medium" => $coverage_medium,
            "coverage_high" => $coverage_high,
            "health_low" => $health_low,
            "health_medium" => $health_medium,
            "health_high" => $health_high,
            "low_strength" => $low_strength,
            "medium_strength" => $medium_strength,
            "high_strength" => $high_strength
        ]
    ];
}

function getPlanDetails($plan) {
    if ($plan == "Basic Protect") {
        return [
            "coverage" => 50000,
            "premium" => 20
        ];
    }

    if ($plan == "Family Protect") {
        return [
            "coverage" => 150000,
            "premium" => 55
        ];
    }

    return [
        "coverage" => 300000,
        "premium" => 100
    ];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $plan_name = trim($_POST['plan_name'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $monthly_income = floatval($_POST['monthly_income'] ?? 0);
    $smoking_status = trim($_POST['smoking_status'] ?? '');
    $existing_illness = trim($_POST['existing_illness'] ?? '');
    $beneficiary_name = trim($_POST['beneficiary_name'] ?? '');
    $beneficiary_relationship = trim($_POST['beneficiary_relationship'] ?? '');

    if ($plan_name == "" || $age <= 0 || $monthly_income <= 0 || $smoking_status == "" || $existing_illness == "" || $beneficiary_name == "" || $beneficiary_relationship == "") {
        $error = "Please fill in all insurance application details.";
    } else {
        $plan = getPlanDetails($plan_name);
        $coverage_amount = $plan['coverage'];
        $base_premium = $plan['premium'];

        $fuzzy = calculateFuzzyInsuranceRisk($age, $monthly_income, $coverage_amount, $smoking_status, $existing_illness);

        $approval_status = $fuzzy['decision'];
        $policy_status = $approval_status == "rejected" ? "rejected" : "active";

        $smoker_loading_rate = $fuzzy['smoker_loading_rate'];
        $illness_loading_rate = $fuzzy['illness_loading_rate'];
        $fuzzy_loading_rate = $fuzzy['fuzzy_loading_rate'];
        $total_loading_rate = $fuzzy['total_loading_rate'];

        $monthly_premium = $approval_status == "rejected" ? 0 : round($base_premium * $fuzzy['premium_multiplier'], 2);
        $next_due_date = $approval_status == "rejected" ? null : date("Y-m-d", strtotime("+1 month"));

        $stmt = mysqli_prepare($conn, "INSERT INTO insurance_policies
            (user_id, plan_name, coverage_amount, base_premium, smoker_loading_rate, illness_loading_rate, fuzzy_loading_rate, total_loading_rate, monthly_premium, beneficiary_name, beneficiary_relationship, smoking_status, existing_illness, monthly_income, age, fuzzy_risk_score, fuzzy_risk_level, approval_status, policy_status, next_due_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        mysqli_stmt_bind_param(
            $stmt,
            "isdddddddssssdisssss",
            $user_id,
            $plan_name,
            $coverage_amount,
            $base_premium,
            $smoker_loading_rate,
            $illness_loading_rate,
            $fuzzy_loading_rate,
            $total_loading_rate,
            $monthly_premium,
            $beneficiary_name,
            $beneficiary_relationship,
            $smoking_status,
            $existing_illness,
            $monthly_income,
            $age,
            $fuzzy['risk_score'],
            $fuzzy['risk_level'],
            $approval_status,
            $policy_status,
            $next_due_date
        );

        if (mysqli_stmt_execute($stmt)) {
            if ($approval_status == "approved") {
                $_SESSION['success'] = "Insurance approved. Risk score: " . $fuzzy['risk_score'] . ". Final premium: RM " . number_format($monthly_premium, 2) . ".";
            } elseif ($approval_status == "approved_higher_premium") {
                $_SESSION['success'] = "Insurance approved with premium loading. Risk score: " . $fuzzy['risk_score'] . ". Final premium: RM " . number_format($monthly_premium, 2) . ".";
            } else {
                $_SESSION['error'] = "Insurance rejected due to high fuzzy risk score: " . $fuzzy['risk_score'] . ".";
            }

            header("Location: /gxbank_html_by_page/pages/insurance.php");
            exit();
        } else {
            $error = "Failed to apply insurance: " . mysqli_error($conn);
        }
    }
}
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Apply Insurance</title>

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

        <a href="/gxbank_html_by_page/pages/insurance.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Back</span>
        </a>

        <h1 class="text-2xl font-bold text-white mb-2">Apply GX Protect</h1>
        <p class="text-slate-400 text-sm mb-6">
            Fuzzy logic will evaluate your insurance risk profile and premium loading.
        </p>

        <?php if($error != "") { ?>
            <div class="bg-red-500/15 text-red-300 rounded-xl p-3 text-sm mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php } ?>

        <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800 mb-4">
            <p class="text-slate-300 font-medium text-sm mb-3">Premium Loading Rules</p>

            <div class="space-y-3 text-sm">
                <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <p class="text-white font-semibold">Non-smoker</p>
                    <p class="text-slate-400 text-xs mt-1">No smoker loading.</p>
                </div>

                <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <p class="text-white font-semibold">Smoker</p>
                    <p class="text-slate-400 text-xs mt-1">+25% premium loading.</p>
                </div>

                <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <p class="text-white font-semibold">Existing Illness</p>
                    <p class="text-slate-400 text-xs mt-1">+35% premium loading.</p>
                </div>

                <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <p class="text-white font-semibold">Medium Fuzzy Risk</p>
                    <p class="text-slate-400 text-xs mt-1">+20% premium loading.</p>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800 mb-4">
            <p class="text-slate-300 font-medium text-sm mb-3">Plans</p>

            <div class="space-y-3 text-sm">
                <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <p class="text-white font-semibold">Basic Protect</p>
                    <p class="text-slate-400 text-xs mt-1">Coverage RM50,000 · Base premium RM20/month</p>
                </div>

                <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <p class="text-white font-semibold">Family Protect</p>
                    <p class="text-slate-400 text-xs mt-1">Coverage RM150,000 · Base premium RM55/month</p>
                </div>

                <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <p class="text-white font-semibold">Premium Protect</p>
                    <p class="text-slate-400 text-xs mt-1">Coverage RM300,000 · Base premium RM100/month</p>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800">
            <form method="POST" class="space-y-4">

                <div>
                    <label class="text-slate-300 text-sm font-medium">Insurance Plan</label>
                    <select name="plan_name"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-sky-400">
                        <option value="Basic Protect">Basic Protect</option>
                        <option value="Family Protect">Family Protect</option>
                        <option value="Premium Protect">Premium Protect</option>
                    </select>
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Age</label>
                    <input type="number" name="age" value="<?php echo htmlspecialchars($user['age']); ?>" required
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-sky-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Monthly Income</label>
                    <input type="number" step="0.01" name="monthly_income" required placeholder="Example: 5000"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-sky-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Smoking Status</label>
                    <select name="smoking_status"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-sky-400">
                        <option value="non_smoker">Non-smoker</option>
                        <option value="smoker">Smoker</option>
                    </select>
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Existing Illness</label>
                    <select name="existing_illness"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-sky-400">
                        <option value="no">No</option>
                        <option value="yes">Yes</option>
                    </select>
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Beneficiary Name</label>
                    <input type="text" name="beneficiary_name" required placeholder="Example: Ahmad Bin Ali"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-sky-400">
                </div>

                <div>
                    <label class="text-slate-300 text-sm font-medium">Beneficiary Relationship</label>
                    <input type="text" name="beneficiary_relationship" required placeholder="Example: Spouse, Child, Parent"
                        class="w-full mt-2 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white outline-none focus:border-sky-400">
                </div>

                <div class="bg-sky-500/15 text-sky-300 rounded-xl p-3 text-sm">
                    Final premium = base premium + smoker loading + illness loading + fuzzy risk loading.
                </div>

                <button type="submit"
                    class="w-full bg-sky-500 hover:bg-sky-600 text-white font-semibold py-3 rounded-xl transition">
                    Apply Insurance
                </button>

            </form>
        </div>

    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>