<?php

App::uses('CoHttpClient', 'Lib');
App::uses('CoPerson', 'Model');

class ItrsscilogonAssigner extends AppModel {
  // Required by COmanage Plugins
  public $cmPluginType = "identifierassigner";

  // SAML IdP entity ID
  protected $idp = 'https://shib-idp.umsystem.edu/idp/shibboleth';
  
  // CILogon OA4MP dbServer endpoint
  protected $oa4mp = 'http://oa4mp-server.cilogon-service.svc.cluster.local:8888';

  /**
   * Assign a new Identifier.
   *
   * @since  COmanage Registry v4.4.0
   * @param  int                              $coId           CO ID for Identifier Assignment
   * @param  IdentifierAssignmentContextEnum  $context        Context in which to assign Identifier
   * @param  int                              $recordId       Record ID of type $context
   * @param  string                           $identifierType Type of identifier to assign
   * @param  string                           $emailType      Type of email address to assign
   * @return string
   * @throws InvalidArgumentException
   */
  
  public function assign($coId, $context, $recordId, $identifierType, $emailType=null) {
    if($context != IdentifierAssignmentContextEnum::CoPerson) {
      throw new InvalidArgumentException('NOT IMPLEMENTED');
    }

    // Pull the CO Person and associated Identifiers.
    $CoPerson = new CoPerson();

    $args = array();
    $args['conditions']['CoPerson.id'] = $recordId;
    $args['contain'][] = 'Identifier';
    $args['contain'][] = 'Name';
    $args['contain'][] = 'EmailAddress';

    $coPerson = $CoPerson->find('first', $args);

    if(empty($coPerson)) {
      throw new InvalidArgumentException(_txt('er.notfound', array(_txt('ct.co_people.1'), $recordId)));
    }

    // Find the eppn and its scope.
    $i = Hash::extract($coPerson['Identifier'], '{n}[type=eppn]');
    $eppn = $i[0]['identifier'] ?? null;
    if(empty($eppn)) {
      $this->log("ItrsscilogonAssigner no eppn Identifier found");
      throw new InvalidArgumentException(_txt('er.itrsscilogonassigner.eppn'));
    }

    list($uid, $scope) = explode('@', $eppn);

    // Find the official email address.
    $m = Hash::extract($coPerson['EmailAddress'], '{n}[type=official]');
    $email = $m[0]['mail'] ?? null;
    if(empty($email)) {
      $this->log("ItrsscilogonAssigner no official EmailAddress found");
      throw new InvalidArgumentException(_txt('er.itrsscilogonassigner.mail'));
    }

    // Find name details.
    $n = Hash::extract($coPerson['Name'], '{n}[type=official]');
    $given = $n[0]['given'] ?? null;
    $family = $n[0]['family'] ?? null;
    if(empty($given) || empty($family)) {
      $this->log("ItrsscilogonAssigner no official Name found");
      throw new InvalidArgumentException(_txt('er.itrsscilogonassigner.name'));
    }

    // Compute the list of eppns we will map to CILogon user identifiers.

    $eppns = array();
    $eppns[] = $eppn;

    if($scope == 'umh.edu' || $scope == 'umkc.edu' || $scope = 'mst.edu' || $scope = 'umsl.edu' || $scope = 'missouri.edu') {
      $eppn = $uid . '@umsystem.edu';
      $eppns[] = $eppn;
    } 

    if($scope == 'missouri.edu') {
      $eppn = $uid . '@mizzou.edu';
      $eppns[] = $eppn;
    }

    // Call out to the dbService with name, email address, idp,
    // and eppn to get a unique CILogon user identifier.

    $Http = new CoHttpClient();
    $Http->setConfig(array('serverurl' => $this->oa4mp));

    $params = array();
    $params['action'] = 'getUser';
    $params['idp'] = $this->idp;
    $params['first_name'] = $given;
    $params['last_name'] = $family;
    $params['email'] = $email;

    $cilogonIdentifiers = array();

    foreach($eppns as $eppn) {
      $params['eppn'] = $eppn;
      $response = $Http->get('/oauth2/dbService', $params);

      if($response->code != 200) {
        $this->log("ItrsscilogonAssigner error invoking dbService. Response was ");
        $this->log(print_r($response, true));
        throw new InvalidArgumentException(_txt('er.itrsscilogonassigner.dbservice', array($eppn)));
      }

      $lines = preg_split("/\r\n|\n|\r/", $response->body);

      foreach($lines as $line) {
        if(str_starts_with($line, 'user_uid')) {
          $cilogonIdentifier = urldecode(explode('=', $line)[1]);
          $cilogonIdentifiers[] = $cilogonIdentifier;
        }
      }
    }

    if(empty($cilogonIdentifiers)) {
        $this->log("ItrsscilogonAssigner no CILogon user identifier returned by dbService");
        throw new InvalidArgumentException(_txt('er.itrsscilogonassigner.none'));
    }

    // We can only return a single Identifier value, so if there is more
    // than one CILogon user identifier generated from more than one ePPN
    // then directly create the additional Identifiers linked to the
    // CoPerson record.
    $n = count($cilogonIdentifiers);

    if($n > 1) {
      for($i = 1; $i < $n; $i++) {
        $data = array();

        $data['Identifier']['identifier'] = $cilogonIdentifiers[$i];
        $data['Identifier']['type'] = IdentifierEnum::OIDCsub;
        $data['Identifier']['status'] = SuspendableStatusEnum::Active;
        $data['Identifier']['co_person_id'] = $recordId;

        // Note that this happens inside of a transaction because this
        // function is called inside of a transaction.
        $CoPerson->Identifier->clear();
        $CoPerson->Identifier->save($data, array('provision' => false));
      }
    }

    return $cilogonIdentifiers[0];
  }
}
