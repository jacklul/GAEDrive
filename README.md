# GAEDrive 

[SabreDAV](http://sabre.io/) on [Google's App Engine](https://cloud.google.com/appengine/).

## Install

- Clone this repository
- Rename `app.yaml.example` to `app.yaml`
- Add new "User" entity to [Datastore](https://cloud.google.com/datastore/) (see below for entity fields)
- Create new [GCP](https://console.cloud.google.com) project and deploy: `gcloud app deploy --project YOUR-PROJECT-NAME --version v1 app.yaml -q`
- Visit `https://YOUR-PROJECT-NAME.appspot.com` to check if everything works

### "User" entity fields

##### Required fields

| field         | type |  description |
| ------------- | ------------- | ------------- |
| "name=" identifier | (string) | User's login username |
| password_hash | (string) | Generated with `bin/password <password>` |

##### Optional fields

| field         | type | description |
| ------------- | ------------- | ------------- |
| display_name | (string) | `{DAV:}displayname` value |
| is_administrator | (bool) | Has access to all directories |
| is_guest | (bool) | Has access only to shared and public directories |
| is_limited | (bool) | Has only access to their private directory and public directories |
| is_read_only | (bool) | Can only do read operations |

## Notes

- You're heavily limited by [Cloud Storage](https://cloud.google.com/storage/) operations limits in free tier
- When you add/remove/edit user you might have to clean Memcache to make the changes take effect immediately
- Passwords are basically md5 hashes - feel free to upgrade this
- Max size of a single file is 32MB (limited by max allowed HTTP request size)

## License

See [LICENSE](LICENSE).
