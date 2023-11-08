# Twin Family Mapper

This module attempts to map twin records by email and assigns a unique family ID.

## Configuration

* Specify the primary and secondary fields for matching twins (email addresses).
* Specify the field for the family_id
* Optionally, you can specify a family_id prefix.  If set as "FAM-" then family IDs will go as FAM-1, FAM-2, ...

All the fields should reside in the same event_id


## Bulk Cleanup
There is an EM page that will do a mass-family assignment for those records that do not already have a family ID.  For a large number of records, this could take a while, so be patient.


