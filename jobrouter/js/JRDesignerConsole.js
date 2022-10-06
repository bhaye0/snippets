var $d = (function () {
  const createProcessTableFields = (fields) => {
    const request = buildBasicRequest("ProcessTable");

    for (const field in fields) {
      request.data.body = buildPtFieldConfiguration(field, fields[field]);
      fetch(request.url, request.data);
    }
  };

  const createStepRules = (rules) => {
    const request = buildBasicRequest("steps_Rules");

    for (const rule in rules) {
      request.data.body = buildRuleConfiguration(field, fields[field]);
      fetch(request.url, request.data);
    }
  };

  const buildBasicRequest = (cmd) => {
    return {
      url: window.location.origin + "/jobrouter/designer.php?cmd=" + cmd,
      data: {
        headers: { "content-type": "application/x-www-form-urlencoded", },
        body: null, method: "POST", mode: "cors", credentials: "include"
      }
    }
  };

  const buildPtFieldConfiguration = (name, field) => {
    return `form_rand=${getRandomNumber()}&csrf_token=${getCsrfToken()}&fieldname=${name}&fieldtype=${field.type}&description=${field.description}&fieldsize=${field.size}&decimal_places=${field.decPlaces}&jr_entrant=${field.entrant}&mode=new&action=save`;
  };

  const buildRuleConfiguration = (name, rule) => {
    return `form_rand=${getRandomNumber()}&csrf_token=${getCsrfToken()}&label=${name}&parent_id=&previous_rule_id=&documentation=&active=${rule.active}&subtable=&split_type=&always=1&field1a=&operator1=0&field1b=&operation1=0&field2a=&operator2=0&field2b=&operation2=0&field3a=&operator3=0&field3b=&condition_string=&condition_function=&condition_function_return_value=1&jr_type_template=&jr_type_1=table&jr_field_1=${rule.assign.field.name}&jr_value_1=${rule.assign.field.value}&assign_maxcount=1&assign_count=1&step=${rule.step}&type=${rule.type}&action=save&mode=new`;
  };

  const getRandomNumber = () => {
    return Math.floor((Math.random() + 1) * 999999999);
  };

  const getCsrfToken = () => {
    return document.getElementById('csrf_token').value;
  };
    
  return {
    processtable: {
      fields: {
        create: createProcessTableFields,
        update: null,
        delete: null,
      }
    },
    subtable: {
      create: null,
      update: null,
      delete: null,
        
      fields: {
        create: null,
        update: null,
        delete: null,
      }
    },
    subtableView: {
      create: null,
      update: null,
      delete: null,

      fields: {
        create: null,
        update: null,
        delete: null,
      }
    },
    step: {
      create: null,
      update: null,
      delete: null
    },
    rule: {
      create: createStepRules(rules),
      update: null,
      delete: null
    }
  }
})();

/*
fetch("https://intranet.synapsis.de/jobrouter/designer.php?cmd=ProcessTable", {
  "body": null,
  "method": "GET",
  "mode": "cors",
  "credentials": "include"
});

$d.processtable.fields.add({
    field1a: { type: 'varchar', description: 'myDescription', size: 50, decPlaces: null, entrant: 1 },
    field2a: { type: 'varchar', description: 'myDescription', size: 50, decPlaces: null, entrant: 1 },
})
*/