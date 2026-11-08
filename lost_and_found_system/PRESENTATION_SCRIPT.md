# Lost and Found System — Presentation Script

*Use this as a speaking guide. Replace the bracketed parts with your name, course, and any details your teacher expects.*

## 1. Opening

Good [morning/afternoon], everyone. My name is [your name], and today I will present our Lost and Found Management System.

The system is designed to make it easier for students to report lost belongings, for security staff to record and manage found items, and for administrators to review ownership claims. It brings these steps into one organized system instead of relying only on verbal inquiries or scattered records.

I will briefly introduce the user roles, demonstrate the main workflow, and explain the Decision Tree feature that can help administrators review claims.

## 2. The problem and our goal

When an item is lost, the owner may not know whether it has been found or where to ask. Staff may also have difficulty keeping track of item details, claim evidence, and whether an item has already been returned.

Our goal is to provide a central place to record lost and found items, help users find possible matches, collect ownership evidence, and keep the review and release process organized.

## 3. User roles

The system has three main roles:

- Students can report lost items, browse found items, view possible matches, submit ownership claims, and follow their claim status.
- Security staff can record found items, manage their storage information, review evidence, and record the physical release of an item after it is authorized.
- Administrators can manage system records and users, review claims, make the final approve-or-reject decision, and manage the Decision Tree model.

Each role has its own pages and permissions, so users see the functions needed for their responsibilities.

## 4. Demonstration: typical workflow

I will now show the main process.

First, a student reports a lost item and enters details such as its category, description, location, and date. When security staff find an item, they record it in the system. The system can then show possible lost-and-found matches based on item details.

A possible match is only a suggestion. If the student believes the item is theirs, they submit a claim and provide ownership details. The claim is then reviewed by an administrator, who considers the evidence and makes the final decision. If the claim is approved, security staff can record the item’s handover to the student.

The system also provides notifications and reports to help users follow activity and help staff keep records.

## 5. Transition to the Decision Tree

There are two features that are easy to confuse. The item-matching feature helps identify which lost report might correspond to a found item. The Decision Tree has a different purpose: it gives an administrator a recommendation about an ownership claim based on the evidence submitted and the private reference details recorded for the item.

## 6. Decision Tree explanation

A Decision Tree is a machine-learning model that makes a decision by following a series of questions, or tests. It is similar to a flowchart. At each branch, it checks one piece of information and follows the branch that fits. After several checks, it reaches a final result called a leaf.

For our ownership review, the system compares six evidence features:

1. Whether the claimed item category matches the found item’s category.
2. How much the claimant’s description overlaps with the found-item description.
3. Whether the reported location matches, is in the same building, or differs.
4. Whether the reported loss date is consistent with the date the item was found.
5. Whether the claimant describes private identifying characteristics recorded by staff.
6. Whether the claimant’s answers match the independently recorded verification answers.

The system converts these comparisons into feature values. For example, category agreement is represented as a match or mismatch, while description and answer similarity use word overlap. If there is not enough information to compare something, that feature is marked unknown rather than treated as a match.

During training, the tree looks at examples that have been independently reviewed and labeled. It tries candidate questions and thresholds and selects splits that separate the labeled outcomes more clearly. In this implementation, it uses weighted Gini impurity to measure how mixed the labels are. In plain language, the tree prefers a question that makes the groups on either side more consistent. It repeats this process until it reaches a stopping limit, such as the maximum depth or too few examples to split safely.

When a new claim is assessed, the system calculates the same six features and follows the learned branches. The leaf gives one of three recommendations: “Likely Legitimate,” “Needs Verification,” or “Suspicious.” These are recommendations based on patterns in the training examples, not a probability and not proof of ownership.

## 7. How training and evaluation are handled

The model learns from claims that have labels based on independent verification. For example, staff may verify a private identifying mark or other evidence. The model’s own recommendation must not be used as the label, because that would make the model appear to confirm its own answer.

The records are separated into training and held-out testing groups. Connected claims—for example, claims involving the same found item or claimant—are kept together so that closely related records do not appear on both sides. The model is fitted using the training data, then checked against the held-out test data. Measures such as accuracy, precision, recall, F1-score, and a confusion matrix summarize how its predictions compare with the independent labels.

The project documentation currently reports that the database does not yet contain enough eligible, independently labeled claim records to train and evaluate a production model. Therefore, if the live system shows “Not classified,” that means a compatible trained model is unavailable or the claim cannot be assessed from comparable evidence. The claim can still be reviewed manually. We should not present a test result or accuracy number unless the Decision Tree page shows one for a successfully trained model.

## 8. Human review and safeguards

The Decision Tree does not approve or reject a claim, and it cannot release an item. The administrator makes the final ownership decision after reviewing the evidence. Security staff record the physical handover only after the claim has been authorized.

This human review matters because text similarity is limited. The system may not understand synonyms or paraphrases, and a matching score cannot prove that a person owns an item. The recommendation is intended to help organize review, not replace staff judgment.

## 9. Closing

To summarize, the Lost and Found Management System organizes item reporting, possible matching, claim submission, administrative review, and item release. The Decision Tree can support claim review by learning patterns from independently verified examples and showing a recommendation based on six ownership-evidence features. The final decision remains with the administrator.

Thank you for listening. I’m ready to answer your questions.

## Quick explanation if asked

**“Explain the Decision Tree in one sentence.”**

It is a flowchart-like model that checks ownership evidence one feature at a time, follows learned yes/no-style branches, and ends with a claim-review recommendation.

**“What are its three outputs?”**

Likely Legitimate, Needs Verification, and Suspicious. They are recommendations, not final decisions.

**“What happens if there is no trained model?”**

The claim can be marked Not classified and reviewed manually; the system should not invent a model result.

**“Does it decide who gets the item?”**

No. The administrator makes the final decision, and security staff record the handover after authorization.

**“How is it different from item matching?”**

Item matching suggests which lost report may correspond to a found item. The Decision Tree assesses evidence in an ownership claim.

**“What does Gini impurity mean?”**

It is a measure of how mixed the outcome labels are in a group. A useful split makes the groups after the split less mixed.

**“What information does the model use?”**

The six evidence features listed above. It does not use personal identifiers such as a student’s name or email as model features.

## Suggested demo order

1. Show the student dashboard and the lost-item report or found-item listings.
2. Show how a student submits or tracks a claim.
3. Show the administrator’s claim-review page and point out the evidence and recommendation area.
4. If available, show the Admin → Decision Tree page. Explain the readiness counts and metrics only if a model has actually been trained.
5. Finish by showing that the administrator makes the decision and security records the release.
