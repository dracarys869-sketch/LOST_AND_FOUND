-- Select your EXISTING application database before running this migration.
-- Additive and rerunnable. No historical rows are changed, labeled, or deleted.

CREATE TABLE IF NOT EXISTS found_item_verification (
    found_item_id INT UNSIGNED NOT NULL,
    unique_characteristics TEXT NULL,
    verification_answers JSON NOT NULL,
    public_description TEXT NULL,
    reference_version INT UNSIGNED NOT NULL DEFAULT 1,
    recorded_by INT UNSIGNED NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (found_item_id),
    CONSTRAINT fk_verification_found FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_verification_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS decision_tree_models (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    version VARCHAR(64) NOT NULL,
    feature_version VARCHAR(32) NOT NULL,
    model_json JSON NOT NULL,
    metrics_json JSON NOT NULL,
    split_json JSON NOT NULL,
    dataset_hash CHAR(64) NOT NULL,
    train_count INT UNSIGNED NOT NULL,
    test_count INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    trained_by INT UNSIGNED NULL,
    trained_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_decision_tree_version (version),
    KEY idx_decision_tree_active (is_active),
    CONSTRAINT fk_tree_trainer FOREIGN KEY (trained_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS claim_assessments (
    claim_id INT UNSIGNED NOT NULL,
    feature_version VARCHAR(32) NOT NULL,
    submitted_json JSON NOT NULL,
    reference_json JSON NOT NULL,
    features_json JSON NOT NULL,
    evidence_json JSON NOT NULL,
    submission_fingerprint CHAR(64) NOT NULL,
    captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    classification ENUM('Likely Legitimate','Needs Verification','Suspicious') NULL,
    prediction_status ENUM('predicted','unavailable','error') NOT NULL DEFAULT 'unavailable',
    prediction_reason VARCHAR(255) NULL,
    model_version VARCHAR(64) NULL,
    prediction_json JSON NULL,
    predicted_at DATETIME NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (claim_id),
    KEY idx_assessment_classification (classification),
    CONSTRAINT fk_assessment_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
    CONSTRAINT fk_assessment_model FOREIGN KEY (model_version) REFERENCES decision_tree_models(version) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS claim_verifications (
    claim_id INT UNSIGNED NOT NULL,
    verification_status ENUM('unverified','verified','inconclusive') NOT NULL DEFAULT 'unverified',
    training_label ENUM('Likely Legitimate','Needs Verification','Suspicious') NULL,
    verification_notes TEXT NULL,
    verified_by INT UNSIGNED NULL,
    verified_at DATETIME NULL,
    final_decision ENUM('approved','rejected') NULL,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    security_notes TEXT NULL,
    security_reviewed_by INT UNSIGNED NULL,
    security_reviewed_at DATETIME NULL,
    PRIMARY KEY (claim_id),
    KEY idx_verified_training (verification_status, training_label),
    CONSTRAINT fk_claim_verification FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
    CONSTRAINT fk_claim_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_claim_decider FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_claim_security_reviewer FOREIGN KEY (security_reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Original images can reveal identifying marks. Only explicitly reviewed images
-- may be public; absence of a row means private. Original files remain intact.
CREATE TABLE IF NOT EXISTS item_photo_visibility (
    photo_id INT UNSIGNED NOT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (photo_id),
    CONSTRAINT fk_photo_visibility FOREIGN KEY (photo_id) REFERENCES item_photos(id) ON DELETE CASCADE,
    CONSTRAINT fk_photo_visibility_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
