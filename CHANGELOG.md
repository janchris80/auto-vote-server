# Changelog

## [1.0.2] - 2023-12-20
### Added
- feature `vote_logs`: logging for every successful each votes
    - record mana left
    - record resource left
    - voter
    - weight of voter
    - followed author
    - author from the post
    - weight of author
    - permlink
    - voted at
    - voting type (scaled or fixed)
    - trailer type (curation, downvote, upvote_post, upvote_comment)

### Changed
- refactor upvote logic
- change the timestamp checking for diffence time post or replies created from the last time voted
- change `database_api.find_accounts` to `condenser_api.get_accounts` which causing bugs (no limit vote)

### Fixed
- fixed from last update where the `database_api.find_accounts` is different from `condenser_api.get_accounts`
- fixed problem of not filtered mana and rc (no limit voting)
