<?php

function getFeeStructure($conn = null, $course_name = '', $major_count = 0) {

    $fees = [
        'tuition_per_unit' => 100.00,
        'misc_fee' => 1000.00,
        'down_payment_percent' => 0.30,
        'lab_fee' => 0 // default
    ];

    // Optional DB override
    if ($conn && $course_name) {
        $stmt = $conn->prepare("SELECT tuition_per_unit, misc_fee FROM course_fees WHERE course_name = ?");
        $stmt->bind_param("s", $course_name);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result) {
            $fees['tuition_per_unit'] = (float)$result['tuition_per_unit'];
            $fees['misc_fee'] = (float)$result['misc_fee'];
        }
    }

    //
    // ✅ FIX: LAB FEE = 500 PER MAJOR SUBJECT
    //
    $fees['lab_fee'] = 500.00 * $major_count;

    return $fees;
}

function calculateAssessment($fees, $total_units) {

    $tuition = $fees['tuition_per_unit'] * $total_units;

    $total_amount = $tuition + $fees['misc_fee'] + $fees['lab_fee'];

    $down_payment = round($total_amount * $fees['down_payment_percent'], 2);

    $balance = $total_amount - $down_payment;

    return [
        'tuition' => $tuition,
        'total_amount' => $total_amount,
        'down_payment' => $down_payment,
        'balance' => $balance
    ];
}
?>