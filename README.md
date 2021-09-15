## Jira Wizzard aka Jizzard 👑

### Use cases:

- Create bulk issues in jira

### Usage

#### Bulk issue creation

- Create a file `template.txt` with your issue body.
- Create a file `projects.yml` with the following format:

```yml
projects:
  # One entry for each project key.
  - PROJKEY1
  - PROJKEY2  
```

Run with

```./bin/jizzard cb```
