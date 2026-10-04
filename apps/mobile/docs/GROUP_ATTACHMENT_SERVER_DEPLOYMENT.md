# Existing group attachment server API

GET /api/v1/groups/{group}/messages/{message}/attachment requires native bearer/device middleware, active canonical membership, matching message group and the existing message policy. Deleted/missing files return 404. Downloads have private,no-store and nosniff headers. File feed metadata exposes relative download identity without storage paths.

Isolated server source 927a5d4d6099448b4eb2bbbc195befa760871d93 is based on main d884cb983c5b002d017b4bcab120840564d8a6af. Targeted run 37210093917 passed the 62 message/session/group/authorization contracts. No migrations or native uploads are added. Physical download acceptance remains pending.
