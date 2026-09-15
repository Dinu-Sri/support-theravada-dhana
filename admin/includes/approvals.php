<?php

function adminProcessApproval($db, $actionId, $decision, $approverId) {
    $actionId = (int)$actionId;
    $approverId = (int)$approverId;
    if ($actionId < 1 || !in_array($decision, ['approve', 'reject'], true)) {
        throw new InvalidArgumentException('Invalid approval request.');
    }

    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    try {
        $action = $db->fetchOne(
            "SELECT * FROM admin_actions WHERE id = ? AND status = 'pending' FOR UPDATE",
            [$actionId]
        );
        if (!$action) {
            throw new DomainException('Action not found or already processed.');
        }

        if ($decision === 'approve') {
            switch ($action['action_type']) {
                case 'booking_update':
                    $newValues = json_decode($action['new_values'], true);
                    $validStatuses = ['pending', 'receipt_submitted', 'payment_pending', 'confirmed', 'completed', 'cancelled'];
                    if (!is_array($newValues) || !in_array($newValues['status'] ?? '', $validStatuses, true)) {
                        throw new DomainException('This approval contains an invalid reservation status.');
                    }

                    $booking = $db->fetchOne("SELECT id FROM bookings WHERE id = ? FOR UPDATE", [(int)$action['target_id']]);
                    if (!$booking) {
                        throw new DomainException('The target reservation no longer exists.');
                    }

                    $db->query(
                        "UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?",
                        [$newValues['status'], (int)$action['target_id']]
                    );
                    break;

                default:
                    throw new DomainException('This action type is not supported and was not approved.');
            }
        }

        $status = $decision === 'approve' ? 'approved' : 'rejected';
        $statement = $pdo->prepare(
            "UPDATE admin_actions
             SET status = ?, approved_by = ?, approved_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        $statement->execute([$status, $approverId, $actionId]);
        if ($statement->rowCount() !== 1) {
            throw new DomainException('The action was processed by another administrator.');
        }

        $pdo->commit();
        return $decision === 'approve'
            ? 'Action approved and applied successfully.'
            : 'Action rejected.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
